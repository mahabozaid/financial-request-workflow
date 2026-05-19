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
        Cache::shouldReceive('lock')
            ->once()
            ->with('idempotency_lock:test-key', 120, null)
            ->andReturn(Mockery::mock(\Illuminate\Contracts\Cache\Lock::class));

        $this->service->acquireLock('test-key');
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
