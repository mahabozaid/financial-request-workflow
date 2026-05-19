<?php

declare(strict_types=1);

namespace App\Domain\Financial\Exceptions;

use DomainException;

final class IdempotencyKeyExistsException extends DomainException
{
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly string $existingRequestId,
    ) {
        parent::__construct(
            sprintf(
                'A FinancialRequest with idempotency key "%s" already exists (id: %s).',
                $idempotencyKey,
                $existingRequestId,
            )
        );
    }
}
