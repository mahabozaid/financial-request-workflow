<?php

declare(strict_types=1);

namespace App\Domain\Financial\States;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Events\FinancialRequestFailed;
use App\Domain\Financial\Models\FinancialRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

final class FailedState extends AbstractFinancialRequestState
{
    public function getAllowedTransitions(): array
    {
        return [
            FinancialRequestStatus::Processing, // Allow manual retry back into processing.
            FinancialRequestStatus::Cancelled,
        ];
    }

    public function onEnter(FinancialRequest $request): void
    {
        Log::error('FinancialRequest failed', [
            'id'       => $request->id,
            'metadata' => $request->metadata,
        ]);

        Event::dispatch(new FinancialRequestFailed($request));
    }

    public function status(): FinancialRequestStatus
    {
        return FinancialRequestStatus::Failed;
    }
}
