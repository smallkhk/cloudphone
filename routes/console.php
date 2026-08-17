<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('crypto:verify-payments')->everyMinute()->withoutOverlapping();
Schedule::command('vmos:sync-instances')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('vmos:sync-skus')->hourly()->withoutOverlapping();
Schedule::command('vmos:sync-email-skus')->hourly()->withoutOverlapping();
