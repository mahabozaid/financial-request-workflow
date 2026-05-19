<?php

declare(strict_types=1);

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Exceptions\InvalidStateTransitionException;
use App\Domain\Financial\Models\FinancialRequest;
use App\Domain\Financial\States\FinancialRequestStateMachine;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeRequest(FinancialRequestStatus $status): FinancialRequest
{
    $model = Mockery::mock(FinancialRequest::class)->makePartial();
    $model->shouldReceive('save')->andReturn(true);
    $model->shouldReceive('getAttribute')->with('id')->andReturn(123);
    $model->status   = $status;
    $model->id       = 123;
    $model->metadata = null;

    return $model;
}

// ---------------------------------------------------------------------------
// Valid transition matrix
// ---------------------------------------------------------------------------

dataset('valid_transitions', [
    'Pending → UnderReview'      => [FinancialRequestStatus::Pending,     FinancialRequestStatus::UnderReview],
    'Pending → Cancelled'        => [FinancialRequestStatus::Pending,     FinancialRequestStatus::Cancelled],
    'UnderReview → Approved'     => [FinancialRequestStatus::UnderReview, FinancialRequestStatus::Approved],
    'UnderReview → Rejected'     => [FinancialRequestStatus::UnderReview, FinancialRequestStatus::Rejected],
    'Approved → Processing'      => [FinancialRequestStatus::Approved,    FinancialRequestStatus::Processing],
    'Approved → Cancelled'       => [FinancialRequestStatus::Approved,    FinancialRequestStatus::Cancelled],
    'Processing → Completed'     => [FinancialRequestStatus::Processing,  FinancialRequestStatus::Completed],
    'Processing → Failed'        => [FinancialRequestStatus::Processing,  FinancialRequestStatus::Failed],
    'Failed → Processing'        => [FinancialRequestStatus::Failed,      FinancialRequestStatus::Processing],
    'Failed → Cancelled'         => [FinancialRequestStatus::Failed,      FinancialRequestStatus::Cancelled],
]);

// ---------------------------------------------------------------------------
// Invalid transition matrix
// ---------------------------------------------------------------------------

dataset('invalid_transitions', [
    'Pending → Completed'         => [FinancialRequestStatus::Pending,    FinancialRequestStatus::Completed],
    'Pending → Failed'            => [FinancialRequestStatus::Pending,    FinancialRequestStatus::Failed],
    'Completed → Processing'      => [FinancialRequestStatus::Completed,  FinancialRequestStatus::Processing],
    'Rejected → Approved'         => [FinancialRequestStatus::Rejected,   FinancialRequestStatus::Approved],
    'Cancelled → Pending'         => [FinancialRequestStatus::Cancelled,  FinancialRequestStatus::Pending],
    'Processing → UnderReview'    => [FinancialRequestStatus::Processing, FinancialRequestStatus::UnderReview],
]);

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('FinancialRequestStateMachine', function () {

    beforeEach(function () {
        $this->machine = new FinancialRequestStateMachine();
    });

    it('allows valid transitions', function (FinancialRequestStatus $from, FinancialRequestStatus $to) {
        Event::fake();

        $request = makeRequest($from);

        $this->machine->transition($request, $to);

        expect($request->status)->toBe($to);
    })->with('valid_transitions');

    it('throws InvalidStateTransitionException for disallowed transitions', function (
        FinancialRequestStatus $from,
        FinancialRequestStatus $to,
    ) {
        $request = makeRequest($from);

        expect(fn () => $this->machine->transition($request, $to))
            ->toThrow(InvalidStateTransitionException::class);
    })->with('invalid_transitions');

    it('sets exception from/to properties correctly', function () {
        $request = makeRequest(FinancialRequestStatus::Completed);

        try {
            $this->machine->transition($request, FinancialRequestStatus::Pending);
            $this->fail('Expected exception not thrown');
        } catch (InvalidStateTransitionException $e) {
            expect($e->from)->toBe(FinancialRequestStatus::Completed)
                ->and($e->to)->toBe(FinancialRequestStatus::Pending)
                ->and($e->requestId)->toBe(123);
        }
    });


    it('reports correct allowed transitions', function () {
        $request = makeRequest(FinancialRequestStatus::Pending);

        $allowed = $this->machine->getAllowedTransitions($request);

        expect($allowed)->toContain(FinancialRequestStatus::UnderReview)
            ->and($allowed)->toContain(FinancialRequestStatus::Cancelled)
            ->and($allowed)->not->toContain(FinancialRequestStatus::Approved);
    });

    it('reports no allowed transitions for terminal states', function (FinancialRequestStatus $terminal) {
        $request = makeRequest($terminal);

        expect($this->machine->getAllowedTransitions($request))->toBeEmpty();
    })->with([
        'Completed' => [FinancialRequestStatus::Completed],
        'Rejected'  => [FinancialRequestStatus::Rejected],
        'Cancelled' => [FinancialRequestStatus::Cancelled],
    ]);

    it('merges metadata on transition', function () {
        Event::fake();

        $request = makeRequest(FinancialRequestStatus::Pending);
        $request->metadata = ['existing_key' => 'value'];

        $this->machine->transition($request, FinancialRequestStatus::UnderReview, ['new_key' => 'new_value']);

        expect($request->metadata)
            ->toHaveKey('existing_key', 'value')
            ->toHaveKey('new_key', 'new_value');
    });

});
