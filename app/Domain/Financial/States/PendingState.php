<?php

declare(strict_types=1);

namespace App\Domain\Financial\States;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Models\FinancialRequest;
use Illuminate\Support\Facades\Log;

final class PendingState extends AbstractFinancialRequestState
{
    public function getAllowedTransitions(): array
    {
        return [
            FinancialRequestStatus::UnderReview,
            FinancialRequestStatus::Cancelled,
        ];
    }

    public function onEnter(FinancialRequest $request): void
    {
        Log::info('FinancialRequest entered Pending state', [
            'id' => $request->id,
            'amount' => $request->amount,
            'currency' => $request->currency,
        ]);
    }

    public function status(): FinancialRequestStatus
    {
        return FinancialRequestStatus::Pending;
    }
}
