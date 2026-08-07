<?php

namespace App\Providers;

use App\Services\Vmos\VmosClient;
use App\Services\Vmos\VmosCloudPhoneService;
use Illuminate\Support\Facades\Schema;
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
        // Older MySQL/MariaDB defaults (common on shared hosting) cap index
        // key length at 767 bytes; utf8mb4 varchar(255) columns exceed that.
        // 191 chars * 4 bytes/char stays safely under the limit.
        Schema::defaultStringLength(191);
    }
}
