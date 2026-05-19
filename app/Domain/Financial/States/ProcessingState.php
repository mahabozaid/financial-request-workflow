<?php

declare(strict_types=1);

namespace App\Domain\Financial\States;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use App\Domain\Financial\Models\FinancialRequest;
use Illuminate\Support\Facades\Log;

final class ProcessingState extends AbstractFinancialRequestState
{
    public function getAllowedTransitions(): array
    {
        return [
            FinancialRequestStatus::Completed,
            FinancialRequestStatus::Failed,
        ];
    }

    public function onEnter(FinancialRequest $request): void
    {
        Log::info('FinancialRequest processing started', ['id' => $request->id]);
    }

    public function status(): FinancialRequestStatus
    {
        return FinancialRequestStatus::Processing;
    }
}
