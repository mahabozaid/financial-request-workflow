<?php

declare(strict_types=1);

namespace App\Domain\Financial\States;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Events\FinancialRequestCompleted;
use App\Domain\Financial\Models\FinancialRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

final class CompletedState extends AbstractFinancialRequestState
{
    public function getAllowedTransitions(): array
    {
        return []; // Terminal state.
    }

    public function onEnter(FinancialRequest $request): void
    {
        Log::info('FinancialRequest completed successfully', [
            'id'                 => $request->id,
            'external_reference' => $request->external_reference,
        ]);

        Event::dispatch(new FinancialRequestCompleted($request));
    }

    public function status(): FinancialRequestStatus
    {
        return FinancialRequestStatus::Completed;
    }
}
