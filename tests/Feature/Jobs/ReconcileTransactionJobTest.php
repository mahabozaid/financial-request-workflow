<?php

declare(strict_types=1);

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Exceptions\ERPConnectionException;
use App\Domain\Financial\Models\FinancialRequest;
use App\Domain\Financial\Repositories\Contracts\FinancialRequestRepositoryInterface;
use App\Domain\Financial\Services\ERPReconciliationService;
use App\Domain\Financial\Services\FinancialRequestStateService;
use App\Domain\Financial\Services\IdempotencyService;
use App\Jobs\ReconcileTransactionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeJobDependencies(
    ?FinancialRequest $request     = null,
    bool $alreadyProcessed        = false,
    ?callable $erpCallback         = null,
): array {
    $request ??= FinancialRequest::factory()->create([
        'status' => FinancialRequestStatus::Approved,
    ]);

    $repository = Mockery::mock(FinancialRequestRepositoryInterface::class);
    $repository->shouldReceive('findOrFail')->andReturn($request);

    $idempotencyService = Mockery::mock(IdempotencyService::class);
    $lock = Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
    $lock->shouldReceive('get')->andReturn(true);
    $lock->shouldReceive('release')->andReturn(true);
    $idempotencyService->shouldReceive('acquireLock')->andReturn($lock);
    $idempotencyService->shouldReceive('hasBeenProcessed')->andReturn($alreadyProcessed);

    if (! $alreadyProcessed) {
        $idempotencyService->shouldReceive('markAsProcessing')->andReturn(null);
    }

    $erpService = Mockery::mock(ERPReconciliationService::class);
    if ($erpCallback !== null) {
        $erpCallback($erpService);
    } else {
        $erpService->shouldReceive('reconcile')->andReturn([
            'erp_reference' => 'ERP-TEST-001',
            'status'        => 'success',
            'processed_at'  => now()->toIso8601String(),
        ]);
        $idempotencyService->shouldReceive('markAsCompleted')->andReturn(null);
    }

    $stateService = app(FinancialRequestStateService::class);

    return [$repository, $erpService, $idempotencyService, $stateService];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('ReconcileTransactionJob', function () {

    it('processes a request and transitions it to Completed', function () {
        Event::fake();

        $request = FinancialRequest::factory()->create([
            'status'          => FinancialRequestStatus::Approved,
            'idempotency_key' => 'idem-001',
        ]);

        [$repository, $erpService, $idempotencyService, $stateService] = makeJobDependencies($request);

        $job = new ReconcileTransactionJob($request->id, 'idem-001');
        $job->handle($repository, $erpService, $idempotencyService, $stateService);

        expect($request->fresh()->status)->toBe(FinancialRequestStatus::Completed);
    });

    it('skips processing when idempotency record shows already completed', function () {
        $request = FinancialRequest::factory()->create([
            'status'          => FinancialRequestStatus::Processing,
            'idempotency_key' => 'idem-already-done',
        ]);

        [$repository, $erpService, $idempotencyService, $stateService] = makeJobDependencies(
            request:          $request,
            alreadyProcessed: true,
        );

        $erpService->shouldNotReceive('reconcile');

        $job = new ReconcileTransactionJob($request->id, 'idem-already-done');
        $job->handle($repository, $erpService, $idempotencyService, $stateService);

        // Status must remain Processing — job was a no-op.
        expect($request->fresh()->status)->toBe(FinancialRequestStatus::Processing);
    });

    it('re-throws ERPConnectionException so Laravel can retry', function () {
        Event::fake();

        $request = FinancialRequest::factory()->create([
            'status'          => FinancialRequestStatus::Approved,
            'idempotency_key' => 'idem-erp-fail',
        ]);

        [$repository, $erpService, $idempotencyService, $stateService] = makeJobDependencies(
            request:     $request,
            erpCallback: function ($erpService) {
                $erpService->shouldReceive('reconcile')
                    ->andThrow(ERPConnectionException::timeout('idem-erp-fail'));
            },
        );

        $idempotencyService->shouldReceive('markAsFailed')->andReturn(null);

        $job = new ReconcileTransactionJob($request->id, 'idem-erp-fail');

        expect(fn () => $job->handle($repository, $erpService, $idempotencyService, $stateService))
            ->toThrow(ERPConnectionException::class);
    });

    it('transitions request to Failed in the failed() hook', function () {
        Event::fake();

        $request = FinancialRequest::factory()->create([
            'status'          => FinancialRequestStatus::Processing,
            'idempotency_key' => 'idem-permanent-fail',
        ]);

        $job = new ReconcileTransactionJob($request->id, 'idem-permanent-fail');
        $job->failed(new RuntimeException('All retries exhausted'));

        expect($request->fresh()->status)->toBe(FinancialRequestStatus::Failed);
    });

    it('releases the lock even when an exception is thrown', function () {
        Event::fake();

        $request = FinancialRequest::factory()->create([
            'status'          => FinancialRequestStatus::Approved,
            'idempotency_key' => 'idem-lock-release',
        ]);

        $lock = Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $lock->shouldReceive('get')->andReturn(true);
        $lock->shouldReceive('release')->once(); // Must be called exactly once (finally block).

        $idempotencyService = Mockery::mock(IdempotencyService::class);
        $idempotencyService->shouldReceive('acquireLock')->andReturn($lock);
        $idempotencyService->shouldReceive('hasBeenProcessed')->andReturn(false);
        $idempotencyService->shouldReceive('markAsProcessing')->andReturn(null);
        $idempotencyService->shouldReceive('markAsFailed')->andReturn(null);

        $repository = Mockery::mock(FinancialRequestRepositoryInterface::class);
        $repository->shouldReceive('findOrFail')->andReturn($request);

        $erpService = Mockery::mock(ERPReconciliationService::class);
        $erpService->shouldReceive('reconcile')->andThrow(new RuntimeException('boom'));

        $stateService = app(FinancialRequestStateService::class);

        $job = new ReconcileTransactionJob($request->id, 'idem-lock-release');

        try {
            $job->handle($repository, $erpService, $idempotencyService, $stateService);
        } catch (RuntimeException) {
            // Expected
        }
    });

    it('has correct backoff sequence', function () {
        $job = new ReconcileTransactionJob('uuid', 'key');

        expect($job->backoff())->toBe([30, 60, 120, 300, 600]);
    });

    it('retries for up to 24 hours', function () {
        $job = new ReconcileTransactionJob('uuid', 'key');

        expect($job->retryUntil()->diffInHours(now()))->toBe(24);
    });

    it('targets the reconciliation queue on the redis connection', function () {
        $job = new ReconcileTransactionJob('uuid', 'key');

        // Queueable stores these in public properties set by onQueue()/onConnection().
        expect($job->queue)->toBe('reconciliation')
            ->and($job->connection)->toBe('redis');
    });

});
