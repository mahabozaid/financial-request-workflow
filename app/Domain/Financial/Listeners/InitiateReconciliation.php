<?php

declare(strict_types=1);

namespace App\Domain\Financial\Listeners;

use App\Domain\Financial\Events\FinancialRequestApproved;
use App\Jobs\ReconcileTransactionJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

final class InitiateReconciliation implements ShouldQueue
{
    public function handle(FinancialRequestApproved $event): void
    {
        $request = $event->request;

        Log::info('InitiateReconciliation: listener called', [
            'request_id' => $request->id,
            'status' => $request->status->value,
        ]);

        ReconcileTransactionJob::dispatch(
            financialRequestId: $request->id,
            idempotencyKey:     "reconcile:{$request->id}",
        );

        Log::info('InitiateReconciliation: job dispatched', [
            'request_id' => $request->id,
        ]);
    }
}
