<?php

declare(strict_types=1);

namespace App\Domain\Financial\DTOs;

use InvalidArgumentException;

final readonly class CreateFinancialRequestData
{
    public function __construct(
        public int $userId,
        public string $amount,
        public string $currency,
        public ?string $externalReference = null,
        public ?array $metadata = null,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userId:            (int) $data['user_id'],
            amount:            (string) $data['amount'],
            currency:          strtoupper((string) $data['currency']),
            externalReference: $data['external_reference'] ?? null,
            metadata:          $data['metadata'] ?? null,
        );
    }

    private function validate(): void
    {
        if (bccomp($this->amount, '0', 4) <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        if (strlen($this->currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a valid ISO 4217 code.');
        }
    }
}
