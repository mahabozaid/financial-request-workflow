<?php

declare(strict_types=1);

use App\Domain\Financial\DTOs\CreateFinancialRequestData;
use App\Domain\Financial\DTOs\TransitionFinancialRequestData;
use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Events\FinancialRequestApproved;
use App\Domain\Financial\Exceptions\InvalidStateTransitionException;
use App\Domain\Financial\Models\FinancialRequest;
use App\Domain\Financial\Services\FinancialRequestService;
use App\Domain\Financial\Services\FinancialRequestStateService;
use App\Jobs\ReconcileTransactionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// FinancialRequestService — creation
// ---------------------------------------------------------------------------

describe('FinancialRequestService', function () {

    it('creates a financial request in Pending status', function () {
        $user    = \App\Models\User::factory()->create();
        $service = app(FinancialRequestService::class);

        $data = new CreateFinancialRequestData(
            userId:    $user->id,
            amount:    '1500.00',
            currency:  'USD',
        );

        $request = $service->create($data);

        expect($request)->toBeInstanceOf(FinancialRequest::class)
            ->and($request->status)->toBe(FinancialRequestStatus::Pending)
            ->and($request->amount)->toBe('1500.0000')
            ->and($request->currency)->toBe('USD');
    });

    it('creates distinct requests with same user', function () {
        $user    = \App\Models\User::factory()->create();
        $service = app(FinancialRequestService::class);

        $first = $service->create(new CreateFinancialRequestData(
            userId:   $user->id,
            amount:   '1000.00',
            currency: 'EUR',
        ));

        $second = $service->create(new CreateFinancialRequestData(
            userId:   $user->id,
            amount:   '1000.00',
            currency: 'EUR',
        ));

        expect($first->id)->not->toBe($second->id)
            ->and(FinancialRequest::count())->toBe(2);
    });

    it('creates distinct requests for different amounts', function () {
        $user    = \App\Models\User::factory()->create();
        $service = app(FinancialRequestService::class);

        $first  = $service->create(new CreateFinancialRequestData($user->id, '500.00', 'GBP'));
        $second = $service->create(new CreateFinancialRequestData($user->id, '600.00', 'GBP'));

        expect($first->id)->not->toBe($second->id)
            ->and(FinancialRequest::count())->toBe(2);
    });

    it('rejects amount of zero', function () {
        expect(fn () => new CreateFinancialRequestData(1, '0', 'USD'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('rejects invalid currency code', function () {
        expect(fn () => new CreateFinancialRequestData(1, '100', 'USDT'))
            ->toThrow(\InvalidArgumentException::class);
    });

});

// ---------------------------------------------------------------------------
// FinancialRequestStateService — transitions
// ---------------------------------------------------------------------------

describe('FinancialRequestStateService', function () {

    it('transitions a request through a valid path', function () {
        Event::fake();

        $financialRequest = FinancialRequest::factory()->create([
            'status' => FinancialRequestStatus::Pending,
        ]);

        $stateService = app(FinancialRequestStateService::class);

        $updated = $stateService->transition(new TransitionFinancialRequestData(
            requestId:  $financialRequest->id,
            targetStatus: FinancialRequestStatus::UnderReview,
        ));

        expect($updated->status)->toBe(FinancialRequestStatus::UnderReview);
        $this->assertDatabaseHas('financial_requests', [
            'id'     => $financialRequest->id,
            'status' => 'under_review',
        ]);
    });

    it('fires FinancialRequestApproved event when transitioning to Approved', function () {
        Event::fake();

        $financialRequest = FinancialRequest::factory()->create([
            'status' => FinancialRequestStatus::UnderReview,
        ]);

        app(FinancialRequestStateService::class)->transition(new TransitionFinancialRequestData(
            requestId:  $financialRequest->id,
            targetStatus: FinancialRequestStatus::Approved,
        ));

        Event::assertDispatched(FinancialRequestApproved::class);
    });

    it('dispatches ReconcileTransactionJob when request is Approved', function () {
        Queue::fake();

        $financialRequest = FinancialRequest::factory()->create([
            'status' => FinancialRequestStatus::UnderReview,
        ]);

        app(FinancialRequestStateService::class)->transition(new TransitionFinancialRequestData(
            requestId:  $financialRequest->id,
            targetStatus: FinancialRequestStatus::Approved,
        ));

        Queue::assertPushed(ReconcileTransactionJob::class, fn ($job) => $job->financialRequestId === $financialRequest->id);
    });

    it('throws InvalidStateTransitionException for illegal transitions', function () {
        $financialRequest = FinancialRequest::factory()->create([
            'status' => FinancialRequestStatus::Completed,
        ]);

        expect(fn () => app(FinancialRequestStateService::class)->transition(
            new TransitionFinancialRequestData(
                requestId:  $financialRequest->id,
                targetStatus: FinancialRequestStatus::Pending,
            )
        ))->toThrow(InvalidStateTransitionException::class);
    });

    it('rolls back the DB transaction if state transition fails', function () {
        $financialRequest = FinancialRequest::factory()->create([
            'status' => FinancialRequestStatus::Completed,
        ]);

        try {
            app(FinancialRequestStateService::class)->transition(new TransitionFinancialRequestData(
                requestId:  $financialRequest->id,
                targetStatus: FinancialRequestStatus::Pending,
            ));
        } catch (InvalidStateTransitionException) {
            // Expected
        }

        // Request must remain Completed — transaction rolled back.
        $this->assertDatabaseHas('financial_requests', [
            'id'     => $financialRequest->id,
            'status' => 'completed',
        ]);
    });

});
