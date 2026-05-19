<?php

declare(strict_types=1);

namespace App\Domain\Financial\Events;

use App\Domain\Financial\Models\FinancialRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class FinancialRequestApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly FinancialRequest $request,
    ) {}
}
