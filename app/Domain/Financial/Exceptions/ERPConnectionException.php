<?php

declare(strict_types=1);

namespace App\Domain\Financial\Exceptions;

use RuntimeException;
use Throwable;

final class ERPConnectionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $idempotencyKey,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function timeout(string $idempotencyKey): self
    {
        return new self(
            message:        'ERP connection timed out.',
            idempotencyKey: $idempotencyKey,
            code:           504,
        );
    }

    public static function unavailable(string $idempotencyKey, Throwable $previous): self
    {
        return new self(
            message:        'ERP service is unavailable: ' . $previous->getMessage(),
            idempotencyKey: $idempotencyKey,
            code:           503,
            previous:       $previous,
        );
    }
}
