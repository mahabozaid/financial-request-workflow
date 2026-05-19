<?php

declare(strict_types=1);

namespace App\Domain\Financial\States;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Models\FinancialRequest;
use Illuminate\Support\Facades\Log;

final class CancelledState extends AbstractFinancialRequestState
{
    public function getAllowedTransitions(): array
    {
        return []; // Terminal state.
    }

    public function onEnter(FinancialRequest $request): void
    {
        Log::info('FinancialRequest cancelled', ['id' => $request->id]);
    }

    public function status(): FinancialRequestStatus
    {
        return FinancialRequestStatus::Cancelled;
    }
}
