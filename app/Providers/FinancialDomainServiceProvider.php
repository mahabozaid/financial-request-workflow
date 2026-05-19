<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Financial\Events\FinancialRequestApproved;
use App\Domain\Financial\Events\FinancialRequestFailed;
use App\Domain\Financial\Listeners\HandleFailedRequest;
use App\Domain\Financial\Listeners\InitiateReconciliation;
use App\Domain\Financial\Listeners\NotifyOnApproval;
use App\Domain\Financial\Repositories\Contracts\FinancialRequestRepositoryInterface;
use App\Domain\Financial\Repositories\EloquentFinancialRequestRepository;
use App\Domain\Financial\Services\ERPReconciliationService;
use App\Domain\Financial\States\FinancialRequestStateMachine;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class FinancialDomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            FinancialRequestRepositoryInterface::class,
            EloquentFinancialRequestRepository::class,
        );

        $this->app->singleton(FinancialRequestStateMachine::class);

        $this->app->singleton(ERPReconciliationService::class, function ($app) {
            return new ERPReconciliationService(
                baseUrl:        config('financial.erp.base_url'),
                apiKey:         config('financial.erp.api_key'),
                timeoutSeconds: config('financial.erp.timeout_seconds', 15),
            );
        });
    }

    public function boot(): void
    {
        Event::listen(FinancialRequestApproved::class, InitiateReconciliation::class);
        Event::listen(FinancialRequestApproved::class, NotifyOnApproval::class);
        Event::listen(FinancialRequestFailed::class,   HandleFailedRequest::class);
    }
}
