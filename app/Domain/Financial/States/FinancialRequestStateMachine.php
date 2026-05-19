<?php

declare(strict_types=1);

namespace App\Domain\Financial\States;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Exceptions\InvalidStateTransitionException;
use App\Domain\Financial\Models\FinancialRequest;
use Illuminate\Support\Facades\Log;

final class FinancialRequestStateMachine
{
    public function transition(
        FinancialRequest $request,
        FinancialRequestStatus $targetStatus,
        ?array $metadata = null,
    ): void {
        $currentState = $request->status->toState();

        if (! $currentState->canTransitionTo($targetStatus)) {
            throw InvalidStateTransitionException::disallowed(
                from: $request->status,
                to:   $targetStatus,
                id:   $request->id,
            );
        }

        $previousStatus = $request->status;
        $targetState    = $targetStatus->toState();

        Log::info('FinancialRequest state transition initiated', [
            'id'   => $request->id,
            'from' => $previousStatus->value,
            'to'   => $targetStatus->value,
        ]);

        $currentState->onExit($request);

        $request->status = $targetStatus;

        if ($metadata !== null) {
            $request->metadata = array_merge($request->metadata ?? [], $metadata);
        }

        $request->save();

        $targetState->onEnter($request);
    }

    public function getAllowedTransitions(FinancialRequest $request): array
    {
        return $request->status->toState()->getAllowedTransitions();
    }
}
