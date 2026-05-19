<?php

declare(strict_types=1);

namespace App\Domain\Financial\Exceptions;

use DomainException;

final class DuplicateTransactionException extends DomainException
{
    public function __construct(
        public readonly string $idempotencyKey,
    ) {
        parent::__construct(
            sprintf('Transaction with idempotency key "%s" has already been processed.', $idempotencyKey)
        );
    }
}
