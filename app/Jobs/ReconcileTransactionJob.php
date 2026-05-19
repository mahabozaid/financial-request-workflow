<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Financial\DTOs\ReconcileTransactionData;
use App\Domain\Financial\DTOs\TransitionFinancialRequestData;
use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Exceptions\ERPConnectionException;
use App\Domain\Financial\Repositories\Contracts\FinancialRequestRepositoryInterface;
use App\Domain\Financial\Services\ERPReconciliationService;
use App\Domain\Financial\Services\FinancialRequestStateService;
use App\Domain\Financial\Services\IdempotencyService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReconcileTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout       = 30;
    public int $maxExceptions = 10;

    public function __construct(
        public readonly int $financialRequestId,
        public readonly string $idempotencyKey,
    ) {
        $this->onQueue('reconciliation')->onConnection('redis');
    }

    public function retryUntil(): Carbon
    {
        return now()->addHours(24);
    }

    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }

    public function handle(
        FinancialRequestRepositoryInterface $repository,
        ERPReconciliationService            $erpService,
        IdempotencyService                  $idempotencyService,
        FinancialRequestStateService        $stateService,
    ): void {
        $context = [
            'id'              => $this->financialRequestId,
            'idempotency_key' => $this->idempotencyKey,
            'attempt'         => $this->attempts(),
        ];

        Log::info('ReconcileTransactionJob: attempt started', $context);

        $lock = $idempotencyService->acquireLock($this->idempotencyKey);

        if (! $lock->get()) {
            Log::warning('ReconcileTransactionJob: could not acquire lock', $context);
            $this->release(30);
            return;
        }

        try {
            if ($idempotencyService->hasBeenProcessed($this->idempotencyKey)) {
                Log::info('ReconcileTransactionJob: already processed — skipping', $context);
                return;
            }

            $request = $repository->findOrFail($this->financialRequestId);

            $idempotencyService->markAsProcessing($this->idempotencyKey);

            DB::transaction(function () use ($request, $stateService): void {
                $stateService->transition(new TransitionFinancialRequestData(
                    requestId:    $request->id,
                    targetStatus: FinancialRequestStatus::Processing,
                    reason:       'Reconciliation job started',
                ));
            });

            $reconcileData = new ReconcileTransactionData(
                financialRequestId: $request->id,
                amount:             $request->amount,
                currency:           $request->currency,
                idempotencyKey:     $this->idempotencyKey,
                externalReference:  $request->external_reference,
                metadata:           $request->metadata,
            );

            $erpResult = $erpService->reconcile($reconcileData);

            DB::transaction(function () use ($request, $erpResult, $stateService, $idempotencyService): void {
                $request->external_reference = $erpResult['erp_reference'] ?? $request->external_reference;
                $request->save();

                $stateService->transition(new TransitionFinancialRequestData(
                    requestId:    $request->id,
                    targetStatus: FinancialRequestStatus::Completed,
                    reason:       'ERP reconciliation succeeded',
                    metadata:     ['erp_result' => $erpResult],
                ));

                $idempotencyService->markAsCompleted($this->idempotencyKey, $erpResult);
            });

            Log::info('ReconcileTransactionJob: completed successfully', $context);

        } catch (ERPConnectionException $e) {
            Log::error('ReconcileTransactionJob: ERP connection failed', [
                'id' => $this->financialRequestId,
                'error' => $e->getMessage(),
            ]);
            $idempotencyService->markAsFailed($this->idempotencyKey, $e->getMessage());
            throw $e;

        } catch (Throwable $e) {
            Log::error('ReconcileTransactionJob: exception', [
                'id' => $this->financialRequestId,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            $idempotencyService->markAsFailed($this->idempotencyKey, $e->getMessage());
            throw $e;

        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ReconcileTransactionJob: permanently failed', [
            'id'    => $this->financialRequestId,
            'error' => $exception->getMessage(),
        ]);

        try {
            app(FinancialRequestStateService::class)->transition(
                new TransitionFinancialRequestData(
                    requestId:    $this->financialRequestId,
                    targetStatus: FinancialRequestStatus::Failed,
                    reason:       'All reconciliation retries exhausted: ' . $exception->getMessage(),
                )
            );
        } catch (Throwable $e) {
            Log::critical('ReconcileTransactionJob: could not transition to Failed', [
                'id'    => $this->financialRequestId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
