<?php

declare(strict_types=1);

namespace App\Domain\Financial\Listeners;

use App\Domain\Financial\Events\FinancialRequestFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

final class HandleFailedRequest implements ShouldQueue
{
    public function handle(FinancialRequestFailed $event): void
    {
        Log::error('HandleFailedRequest: listener called', [
            'request_id' => $event->request->id,
        ]);

        Log::error('HandleFailedRequest: FinancialRequest entered failed state', [
            'id'       => $event->request->id,
            'amount'   => $event->request->amount,
            'currency' => $event->request->currency,
            'metadata' => $event->request->metadata,
        ]);
    }
}
