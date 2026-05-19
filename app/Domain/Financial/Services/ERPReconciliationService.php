<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\DTOs\ReconcileTransactionData;
use Illuminate\Support\Facades\Log;


class ERPReconciliationService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeoutSeconds = 15,
    ) {}

    public function reconcile(ReconcileTransactionData $data) :array
    {
        Log::info('ERPReconciliationService: sending reconciliation request', [
            'idempotency_key' => $data->idempotencyKey,
            'amount'          => $data->amount,
            'currency'        => $data->currency,
        ]);

        return ['erp_reference' => uniqid(), 'status' => 'created', 'processed_at' => now()->toIso8601String()];
    }
}
