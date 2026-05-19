<?php

declare(strict_types=1);

namespace App\Domain\Financial\States;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Events\FinancialRequestApproved;
use App\Domain\Financial\Models\FinancialRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

final class ApprovedState extends AbstractFinancialRequestState
{
    public function getAllowedTransitions(): array
    {
        return [
            FinancialRequestStatus::Processing,
            FinancialRequestStatus::Cancelled,
        ];
    }

    public function onEnter(FinancialRequest $request): void
    {
        Log::info('FinancialRequest approved', ['id' => $request->id]);

        Event::dispatch(new FinancialRequestApproved($request));
    }

    public function status(): FinancialRequestStatus
    {
        return FinancialRequestStatus::Approved;
    }
}
