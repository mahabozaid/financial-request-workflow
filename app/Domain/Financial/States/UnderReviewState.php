<?php

declare(strict_types=1);

namespace App\Domain\Financial\States;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Models\FinancialRequest;
use Illuminate\Support\Facades\Log;

final class UnderReviewState extends AbstractFinancialRequestState
{
    public function getAllowedTransitions(): array
    {
        return [
            FinancialRequestStatus::Approved,
            FinancialRequestStatus::Rejected,
        ];
    }

    public function onEnter(FinancialRequest $request): void
    {
        Log::info('FinancialRequest is now under review', ['id' => $request->id]);
    }

    public function status(): FinancialRequestStatus
    {
        return FinancialRequestStatus::UnderReview;
    }
}
