<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IdempotencyService
{
    private const LOCK_TTL_SECONDS    = 120;
    private const RECORD_TTL_SECONDS  = 86_400 * 7; // 7 days

    public function acquireLock(string $key): \Illuminate\Contracts\Cache\Lock
    {
        return Cache::lock(
            name:    $this->lockKey($key),
            seconds: self::LOCK_TTL_SECONDS,
            owner:   null,
        );
    }

    public function hasBeenProcessed(string $key): bool
    {
        return DB::table('idempotency_records')
            ->where('idempotency_key', $key)
            ->where('status', 'completed')
            ->exists();
    }

    public function markAsProcessing(string $key): void
    {
        DB::table('idempotency_records')->updateOrInsert(
            ['idempotency_key' => $key],
            [
                'status'     => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
                'expires_at' => now()->addSeconds(self::RECORD_TTL_SECONDS),
            ],
        );
    }

    public function markAsCompleted(string $key, array $result = []): void
    {
        DB::table('idempotency_records')
            ->where('idempotency_key', $key)
            ->update([
                'status'       => 'completed',
                'result'       => json_encode($result),
                'completed_at' => now(),
                'updated_at'   => now(),
            ]);
    }

    public function markAsFailed(string $key, string $reason): void
    {
        DB::table('idempotency_records')
            ->where('idempotency_key', $key)
            ->update([
                'status'     => 'failed',
                'result'     => json_encode(['error' => $reason]),
                'updated_at' => now(),
            ]);
    }

    public function getResult(string $key): ?array
    {
        $record = DB::table('idempotency_records')
            ->where('idempotency_key', $key)
            ->first();

        if ($record === null || $record->result === null) {
            return null;
        }

        return json_decode($record->result, associative: true);
    }

    private function lockKey(string $key): string
    {
        return "idempotency_lock:{$key}";
    }
}
