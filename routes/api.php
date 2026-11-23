<?php

use App\Http\Controllers\Api\AccessKeyVerificationController;
use App\Http\Controllers\Api\Mobile\AuthController as MobileAuth;
use App\Http\Controllers\Api\Mobile\DataController as MobileData;
use App\Http\Controllers\Api\Mobile\LiveScreenController as MobileLive;
use App\Http\Controllers\CloudDriveController;
use App\Http\Controllers\CloudInstanceController;
use App\Http\Controllers\CloudNumberController;
use App\Http\Controllers\CustomerProxyController;
use App\Http\Controllers\DeviceControlController;
use App\Http\Controllers\EmailAccountController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PhoneNumberController;
use App\Http\Controllers\VmosWebhookController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

// Configured as the callback URL in the VMOS console (Developer -> API -> Callback URL).
// Append ?token=<CLOUDPHONE_WEBHOOK_TOKEN> when setting it there, since VMOS does not
// itself sign/authenticate callback requests.
Route::post('/vmos/callback', [VmosWebhookController::class, 'handle'])->name('vmos.callback');

// Called by the desktop app on launch — not by the website itself, which stays unlocked.
Route::post('/access-keys/verify', [AccessKeyVerificationController::class, 'verify'])
    ->middleware('throttle:20,1')
    ->name('access-keys.verify');

/*
|--------------------------------------------------------------------------
| iPhone app
|--------------------------------------------------------------------------
| Reads come from Api\Mobile\DataController. Every change goes through the
| website's own controllers (wrapped by MobileActionResponse into JSON), so
| the app and the site share one set of business rules.
*/
Route::prefix('mobile/v1')->name('mobile.')->group(function () {
    Route::post('auth/register', [MobileAuth::class, 'register'])->middleware('throttle:10,1')->name('auth.register');
    Route::post('auth/login', [MobileAuth::class, 'login'])->middleware('throttle:10,1')->name('auth.login');
    Route::post('auth/verify-otp', [MobileAuth::class, 'verifyOtp'])->middleware('throttle:10,1')->name('auth.verify-otp');
    Route::post('auth/resend-otp', [MobileAuth::class, 'resendOtp'])->middleware('throttle:5,1')->name('auth.resend-otp');

    // The live screen page the app embeds, reached by a signed expiring link.
    Route::get('live/{instance}', [MobileLive::class, 'page'])->middleware('signed')->name('live');
    Route::post('live/{instance}/token', [MobileLive::class, 'token'])->middleware(['signed', 'throttle:30,1'])->name('live.token');

    Route::middleware('auth.app')->group(function () {
        Route::post('auth/logout', [MobileAuth::class, 'logout'])->name('auth.logout');

        Route::get('me', [MobileData::class, 'me'])->name('me');
        Route::get('devices', [MobileData::class, 'devices'])->name('devices');
        Route::get('devices/{instance}', [MobileData::class, 'device'])->name('device');
        Route::post('devices/{instance}/live', [MobileLive::class, 'link'])->name('device.live');
        Route::get('catalog', [MobileData::class, 'catalog'])->name('catalog');
        Route::get('products/{type}', [MobileData::class, 'products'])->name('products');
        Route::get('orders', [MobileData::class, 'orders'])->name('orders');
        Route::get('orders/{order}', [MobileData::class, 'order'])->name('order');
        Route::get('wallet', [MobileData::class, 'wallet'])->name('wallet');
        Route::get('cloud-drive', [MobileData::class, 'cloudDrive'])->name('cloud-drive');
    });

    Route::middleware(['mobile.action', 'auth.app'])->name('action.')->group(function () {
        Route::post('devices/{instance}/restart', [CloudInstanceController::class, 'restart'])->name('restart');
        Route::post('devices/{instance}/reset', [CloudInstanceController::class, 'reset'])->name('reset');
        Route::post('devices/{instance}/screenshot', [CloudInstanceController::class, 'screenshot'])->name('screenshot');
        Route::post('devices/{instance}/sim', [DeviceControlController::class, 'updateSim'])->name('sim');
        Route::post('devices/{instance}/gps', [DeviceControlController::class, 'updateGps'])->name('gps');
        Route::post('devices/{instance}/locale', [DeviceControlController::class, 'updateLocale'])->name('locale');
        Route::post('devices/{instance}/new-device', [DeviceControlController::class, 'newDevice'])->name('new-device');
        Route::post('devices/{instance}/proxy', [DeviceControlController::class, 'setProxy'])->name('proxy');
        Route::post('devices/{instance}/proxy/test', [DeviceControlController::class, 'testProxy'])->name('proxy.test');
        Route::delete('devices/{instance}/proxy', [DeviceControlController::class, 'clearProxy'])->name('proxy.clear');
        Route::post('devices/{instance}/apps/install', [DeviceControlController::class, 'installApp'])->name('apps.install');
        Route::post('devices/{instance}/apps/action', [DeviceControlController::class, 'appAction'])->name('apps.action');
        Route::post('devices/{instance}/apps/refresh', [DeviceControlController::class, 'refreshApps'])->name('apps.refresh');
        Route::post('devices/{instance}/adb', [DeviceControlController::class, 'toggleAdb'])->name('adb');

        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::post('orders/{order}/payment', [OrderController::class, 'submitPayment'])->name('orders.payment');

        Route::post('wallet/deposits', [WalletController::class, 'deposit'])->name('wallet.deposit');
        Route::post('wallet/deposits/{walletDeposit}/tx', [WalletController::class, 'submitTxHash'])->name('wallet.deposit.tx');

        Route::post('email-accounts/{emailAccount}/refresh', [EmailAccountController::class, 'refresh'])->name('email-accounts.refresh');
        Route::post('phone-numbers/{phoneNumber}/refresh', [PhoneNumberController::class, 'refresh'])->name('phone-numbers.refresh');

        Route::post('cloud-numbers/{cloudNumber}/bind', [CloudNumberController::class, 'bind'])->name('cloud-numbers.bind');
        Route::post('cloud-numbers/{cloudNumber}/bind-status', [CloudNumberController::class, 'bindStatus'])->name('cloud-numbers.bind-status');
        Route::post('cloud-numbers/{cloudNumber}/auto-renew', [CloudNumberController::class, 'toggleAutoRenew'])->name('cloud-numbers.auto-renew');
        Route::post('cloud-numbers/{cloudNumber}/release', [CloudNumberController::class, 'release'])->name('cloud-numbers.release');
        Route::post('cloud-numbers/{cloudNumber}/sms', [CloudNumberController::class, 'refreshSms'])->name('cloud-numbers.sms');

        Route::post('proxies', [CustomerProxyController::class, 'store'])->name('proxies.store');
        Route::put('proxies/{proxy}', [CustomerProxyController::class, 'update'])->name('proxies.update');
        Route::delete('proxies/{proxy}', [CustomerProxyController::class, 'destroy'])->name('proxies.destroy');
        Route::post('proxies/{proxy}/attach', [CustomerProxyController::class, 'attach'])->name('proxies.attach');
        Route::post('proxies/{proxy}/detach', [CustomerProxyController::class, 'detach'])->name('proxies.detach');
        Route::post('proxies/{proxy}/test', [CustomerProxyController::class, 'test'])->name('proxies.test');

        Route::post('cloud-drive/upload', [CloudDriveController::class, 'upload'])->name('cloud-drive.upload');
        Route::delete('cloud-drive/file', [CloudDriveController::class, 'destroy'])->name('cloud-drive.delete');
        Route::post('cloud-drive/backup', [CloudDriveController::class, 'backup'])->name('cloud-drive.backup');
        Route::post('cloud-drive/backup/{task}/progress', [CloudDriveController::class, 'backupProgress'])->name('cloud-drive.backup-progress');
    });
});
