<?php

declare(strict_types=1);

namespace App\Domain\Financial\DTOs;

use App\Domain\Financial\Enums\FinancialRequestStatus;

final readonly class TransitionFinancialRequestData
{
    public function __construct(
        public int $requestId,
        public FinancialRequestStatus $targetStatus,
        public ?string $reason = null,
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            requestId:    (int) $data['request_id'],
            targetStatus: FinancialRequestStatus::from($data['target_status']),
            reason:       $data['reason'] ?? null,
            metadata:     $data['metadata'] ?? null,
        );
    }
}
