<?php

use App\Http\Controllers\Api\AccessKeyVerificationController;
use App\Http\Controllers\VmosWebhookController;
use Illuminate\Support\Facades\Route;

// Configured as the callback URL in the VMOS console (Developer -> API -> Callback URL).
// Append ?token=<CLOUDPHONE_WEBHOOK_TOKEN> when setting it there, since VMOS does not
// itself sign/authenticate callback requests.
Route::post('/vmos/callback', [VmosWebhookController::class, 'handle'])->name('vmos.callback');

// Called by the desktop app on launch — not by the website itself, which stays unlocked.
Route::post('/access-keys/verify', [AccessKeyVerificationController::class, 'verify'])
    ->middleware('throttle:20,1')
    ->name('access-keys.verify');
