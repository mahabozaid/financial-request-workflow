<?php

declare(strict_types=1);

namespace App\Domain\Financial\Exceptions;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use DomainException;

final class InvalidStateTransitionException extends DomainException
{
    public function __construct(
        string $message,
        public readonly FinancialRequestStatus $from,
        public readonly FinancialRequestStatus $to,
        public readonly int $requestId,
    ) {
        parent::__construct($message);
    }

    public static function disallowed(
        FinancialRequestStatus $from,
        FinancialRequestStatus $to,
        int $id,
    ): self {
        return new self(
            message:   sprintf(
                'Cannot transition FinancialRequest [%d] from "%s" to "%s".',
                $id,
                $from->value,
                $to->value,
            ),
            from:      $from,
            to:        $to,
            requestId: $id,
        );
    }
}
