<?php

declare(strict_types=1);

namespace App\Domain\Financial\States\Contracts;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Models\FinancialRequest;

interface FinancialRequestStateContract
{
    /**
     * Returns the statuses this state may transition into.
     *
     * @return array<int, FinancialRequestStatus>
     */
    public function getAllowedTransitions(): array;

    public function canTransitionTo(FinancialRequestStatus $status): bool;

    /**
     * Hook executed after the model enters this state.
     * Suitable for side-effects (logging, notifications, etc.).
     */
    public function onEnter(FinancialRequest $request): void;

    /**
     * Hook executed just before the model leaves this state.
     */
    public function onExit(FinancialRequest $request): void;

    public function status(): FinancialRequestStatus;
}
