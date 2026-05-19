<?php

declare(strict_types=1);

use App\Domain\Financial\Services\IdempotencyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

describe('IdempotencyService', function () {

    beforeEach(function () {
        $this->service = new IdempotencyService();
    });

    // -------------------------------------------------------------------------
    // Lock acquisition
    // -------------------------------------------------------------------------

    it('acquires a Redis lock for the given key', function () {
        $mockLock  = Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $mockStore = Mockery::mock(\Illuminate\Cache\Repository::class);
        $mockStore->shouldReceive('lock')
            ->once()
            ->withAnyArgs()
            ->andReturnUsing(function (string $name, int $seconds, mixed $owner) use ($mockLock) {
                expect($name)->toBe('idempotency_lock:test-key')
                    ->and($seconds)->toBe(120)
                    ->and($owner)->toBeNull();

                return $mockLock;
            });

        Cache::swap($mockStore);

        $result = $this->service->acquireLock('test-key');

        expect($result)->toBe($mockLock);
    });

    // -------------------------------------------------------------------------
    // hasBeenProcessed
    // -------------------------------------------------------------------------

    it('returns true when a completed record exists', function () {
        DB::shouldReceive('table')->with('idempotency_records')->andReturnSelf();
        DB::shouldReceive('where')->with('idempotency_key', 'done-key')->andReturnSelf();
        DB::shouldReceive('where')->with('status', 'completed')->andReturnSelf();
        DB::shouldReceive('exists')->andReturn(true);

        expect($this->service->hasBeenProcessed('done-key'))->toBeTrue();
    });

    it('returns false when no completed record exists', function () {
        DB::shouldReceive('table')->with('idempotency_records')->andReturnSelf();
        DB::shouldReceive('where')->with('idempotency_key', 'new-key')->andReturnSelf();
        DB::shouldReceive('where')->with('status', 'completed')->andReturnSelf();
        DB::shouldReceive('exists')->andReturn(false);

        expect($this->service->hasBeenProcessed('new-key'))->toBeFalse();
    });

    // -------------------------------------------------------------------------
    // markAsCompleted / getResult
    // -------------------------------------------------------------------------

    it('persists completed status with result', function () {
        DB::shouldReceive('table')->with('idempotency_records')->andReturnSelf();
        DB::shouldReceive('where')->with('idempotency_key', 'erp-key-1')->andReturnSelf();
        DB::shouldReceive('update')->once()->withArgs(function ($args) {
            return $args['status'] === 'completed'
                && isset($args['result'])
                && json_decode($args['result'], true) === ['erp_reference' => 'ERP-001'];
        })->andReturn(1);

        $this->service->markAsCompleted('erp-key-1', ['erp_reference' => 'ERP-001']);
    });

});
