<?php

declare(strict_types=1);

namespace App\Domain\Financial\Models;

use App\Domain\Financial\Enums\FinancialRequestStatus;
use Database\Factories\FinancialRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int                    $id
 * @property string                 $amount
 * @property string                 $currency
 * @property int                    $user_id
 * @property FinancialRequestStatus $status
 * @property string|null            $external_reference
 * @property array|null             $metadata
 * @property \Carbon\Carbon         $created_at
 * @property \Carbon\Carbon         $updated_at
 * @property \Carbon\Carbon|null    $deleted_at
 */
class FinancialRequest extends Model
{
    /** @use HasFactory<FinancialRequestFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): FinancialRequestFactory
    {
        return FinancialRequestFactory::new();
    }

    protected $table = 'financial_requests';

    protected $fillable = [
        'amount',
        'currency',
        'user_id',
        'status',
        'external_reference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status'   => FinancialRequestStatus::class,
            'metadata' => 'array',
            'amount'   => 'decimal:4',
        ];
    }

    public function scopeByStatus($query, FinancialRequestStatus $status): void
    {
        $query->where('status', $status->value);
    }

    public function scopeForUser($query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    public function scopePending($query): void
    {
        $query->where('status', FinancialRequestStatus::Pending->value);
    }

    public function scopeProcessing($query): void
    {
        $query->where('status', FinancialRequestStatus::Processing->value);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isInStatus(FinancialRequestStatus ...$statuses): bool
    {
        return in_array($this->status, $statuses, strict: true);
    }
}
