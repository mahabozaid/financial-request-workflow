<?php

declare(strict_types=1);

namespace App\Domain\Financial\Enums;

use App\Domain\Financial\States\ApprovedState;
use App\Domain\Financial\States\CancelledState;
use App\Domain\Financial\States\CompletedState;
use App\Domain\Financial\States\FailedState;
use App\Domain\Financial\States\PendingState;
use App\Domain\Financial\States\ProcessingState;
use App\Domain\Financial\States\RejectedState;
use App\Domain\Financial\States\UnderReviewState;
use App\Domain\Financial\States\Contracts\FinancialRequestStateContract;

enum FinancialRequestStatus: string
{
    case Pending     = 'pending';
    case UnderReview = 'under_review';
    case Approved    = 'approved';
    case Rejected    = 'rejected';
    case Processing  = 'processing';
    case Completed   = 'completed';
    case Failed      = 'failed';
    case Cancelled   = 'cancelled';

    public function toState(): FinancialRequestStateContract
    {
        return match ($this) {
            self::Pending     => new PendingState(),
            self::UnderReview => new UnderReviewState(),
            self::Approved    => new ApprovedState(),
            self::Rejected    => new RejectedState(),
            self::Processing  => new ProcessingState(),
            self::Completed   => new CompletedState(),
            self::Failed      => new FailedState(),
            self::Cancelled   => new CancelledState(),
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Rejected, self::Completed, self::Cancelled => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending     => 'Pending',
            self::UnderReview => 'Under Review',
            self::Approved    => 'Approved',
            self::Rejected    => 'Rejected',
            self::Processing  => 'Processing',
            self::Completed   => 'Completed',
            self::Failed      => 'Failed',
            self::Cancelled   => 'Cancelled',
        };
    }
}
