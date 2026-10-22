<?php

namespace App\Providers;

use App\Sync\DispatchesSyncJobs;
use App\Sync\ExternalCrmClient;
use App\Sync\HttpExternalCrmClient;
use App\Sync\InProcessExternalCrmClient;
use App\Sync\QueueSyncDispatcher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DispatchesSyncJobs::class, QueueSyncDispatcher::class);
        $this->app->bind(ExternalCrmClient::class, function ($app) {
            return config('sync.external_crm.transport') === 'inprocess'
                ? $app->make(InProcessExternalCrmClient::class)
                : $app->make(HttpExternalCrmClient::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
