<?php

declare(strict_types=1);

namespace App\Domain\Financial\Listeners;

use App\Domain\Financial\Events\FinancialRequestApproved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

final class NotifyOnApproval implements ShouldQueue
{
    public function handle(FinancialRequestApproved $event): void
    {
        Log::info('NotifyOnApproval: listener called', [
            'request_id' => $event->request->id,
        ]);

        Log::info('NotifyOnApproval: notifying user of approval', [
            'id'      => $event->request->id,
            'user_id' => $event->request->user_id,
        ]);
    }
}
