<?php

namespace App\Providers;

use App\Services\Vmos\VmosClient;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(VmosClient::class);
        $this->app->singleton(VmosCloudPhoneService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
