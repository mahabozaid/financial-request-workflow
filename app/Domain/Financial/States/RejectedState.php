<?php

declare(strict_types=1);

namespace App\Domain\Financial\States;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Models\FinancialRequest;
use Illuminate\Support\Facades\Log;

final class RejectedState extends AbstractFinancialRequestState
{
    public function getAllowedTransitions(): array
    {
        return []; // Terminal state — no further transitions allowed.
    }

    public function onEnter(FinancialRequest $request): void
    {
        Log::warning('FinancialRequest rejected', [
            'id'     => $request->id,
            'metadata' => $request->metadata,
        ]);
    }

    public function status(): FinancialRequestStatus
    {
        return FinancialRequestStatus::Rejected;
    }
}
