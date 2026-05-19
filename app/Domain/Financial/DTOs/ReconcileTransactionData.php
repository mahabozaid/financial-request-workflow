<?php

declare(strict_types=1);

namespace App\Domain\Financial\DTOs;

final readonly class ReconcileTransactionData
{
    public function __construct(
        public int $financialRequestId,
        public string $amount,
        public string $currency,
        public string $idempotencyKey,
        public ?string $externalReference = null,
        public ?array $metadata = null,
    ) {}

    public function toErpPayload(): array
    {
        return [
            'transaction_id'     => $this->financialRequestId,
            'amount'             => $this->amount,
            'currency'           => $this->currency,
            'idempotency_key'    => $this->idempotencyKey,
            'external_reference' => $this->externalReference,
            'metadata'           => $this->metadata ?? [],
        ];
    }
}
