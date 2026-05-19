<?php

declare(strict_types=1);

namespace App\Domain\Financial\States;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Models\FinancialRequest;
use App\Domain\Financial\States\Contracts\FinancialRequestStateContract;

abstract class AbstractFinancialRequestState implements FinancialRequestStateContract
{
    public function canTransitionTo(FinancialRequestStatus $status): bool
    {
        return in_array($status, $this->getAllowedTransitions(), strict: true);
    }

    public function onEnter(FinancialRequest $request): void
    {
        // No-op by default — concrete states override when needed.
    }

    public function onExit(FinancialRequest $request): void
    {
        // No-op by default — concrete states override when needed.
    }
}
