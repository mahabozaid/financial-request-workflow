<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FinancialRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public auth routes
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/login',  [AuthController::class, 'login'])->name('login');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth:sanctum');
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {

        Route::prefix('financial-requests')->name('financial-requests.')->group(function () {
            Route::get('/',                         [FinancialRequestController::class, 'index'])->name('index');
            Route::post('/', [FinancialRequestController::class, 'store'])->name('store')->middleware('idempotency');
            Route::get('/{id}',                     [FinancialRequestController::class, 'show'])->name('show');
            Route::post('/{id}/transition',         [FinancialRequestController::class, 'transition'])->name('transition');
            Route::get('/{id}/allowed-transitions', [FinancialRequestController::class, 'allowedTransitions'])->name('allowed-transitions');
        });

    });

});
