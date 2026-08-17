<?php

use App\Http\Controllers\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DeviceController as AdminDeviceController;
use App\Http\Controllers\Admin\DiagnosticsController as AdminDiagnosticsController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProxyController as AdminProxyController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SkuController as AdminSkuController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CloudInstanceController;
use App\Http\Controllers\DeviceControlController;
use App\Http\Controllers\EmailAccountController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PhoneNumberController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
Route::get('/email-accounts', [EmailAccountController::class, 'index'])->name('email-accounts.index');
Route::get('/phone-numbers', [PhoneNumberController::class, 'index'])->name('phone-numbers.index');

// Live chat — open to guests as well as customers.
Route::get('/chat/history', [ChatController::class, 'history'])->name('chat.history');
Route::post('/chat', [ChatController::class, 'store'])->middleware('throttle:30,1')->name('chat.store');
Route::post('/chat/reset', [ChatController::class, 'reset'])->name('chat.reset');

Route::get('/dashboard', function () {
    return redirect()->route('instances.index');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/payment', [OrderController::class, 'submitPayment'])->name('orders.payment');

    Route::get('/instances', [CloudInstanceController::class, 'index'])->name('instances.index');
    Route::post('/instances/{instance}/restart', [CloudInstanceController::class, 'restart'])->name('instances.restart');
    Route::post('/instances/{instance}/reset', [CloudInstanceController::class, 'reset'])->name('instances.reset');
    Route::post('/instances/{instance}/screenshot', [CloudInstanceController::class, 'screenshot'])->name('instances.screenshot');

    // Per-device control panel
    Route::get('/instances/{instance}', [DeviceControlController::class, 'show'])->name('instances.show');
    Route::post('/instances/{instance}/screen-token', [DeviceControlController::class, 'screenToken'])->name('instances.screen-token');
    Route::post('/instances/{instance}/sim', [DeviceControlController::class, 'updateSim'])->name('instances.sim');
    Route::post('/instances/{instance}/gps', [DeviceControlController::class, 'updateGps'])->name('instances.gps');
    Route::post('/instances/{instance}/locale', [DeviceControlController::class, 'updateLocale'])->name('instances.locale');
    Route::post('/instances/{instance}/new-device', [DeviceControlController::class, 'newDevice'])->name('instances.new-device');
    Route::post('/instances/{instance}/proxy', [DeviceControlController::class, 'setProxy'])->name('instances.proxy');
    Route::post('/instances/{instance}/proxy/test', [DeviceControlController::class, 'testProxy'])->name('instances.proxy.test');
    Route::delete('/instances/{instance}/proxy', [DeviceControlController::class, 'clearProxy'])->name('instances.proxy.clear');
    Route::post('/instances/{instance}/apps/install', [DeviceControlController::class, 'installApp'])->name('instances.apps.install');
    Route::post('/instances/{instance}/apps/action', [DeviceControlController::class, 'appAction'])->name('instances.apps.action');
    Route::post('/instances/{instance}/apps/refresh', [DeviceControlController::class, 'refreshApps'])->name('instances.apps.refresh');
    Route::post('/instances/{instance}/adb', [DeviceControlController::class, 'toggleAdb'])->name('instances.adb');

    Route::post('/email-accounts/{emailAccount}/refresh', [EmailAccountController::class, 'refresh'])->name('email-accounts.refresh');
    Route::post('/phone-numbers/{phoneNumber}/refresh', [PhoneNumberController::class, 'refresh'])->name('phone-numbers.refresh');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Plans & pricing
    Route::get('/skus', [AdminSkuController::class, 'index'])->name('skus.index');
    Route::patch('/skus/{sku}', [AdminSkuController::class, 'update'])->name('skus.update');
    Route::post('/skus/sync', [AdminSkuController::class, 'sync'])->name('skus.sync');
    Route::post('/skus/sync-email', [AdminSkuController::class, 'syncEmail'])->name('skus.sync-email');
    Route::post('/skus/sync-sms', [AdminSkuController::class, 'syncSms'])->name('skus.sync-sms');
    Route::post('/skus/bulk-markup', [AdminSkuController::class, 'bulkMarkup'])->name('skus.bulk-markup');
    Route::post('/skus/bulk-status', [AdminSkuController::class, 'bulkStatus'])->name('skus.bulk-status');

    // Orders
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/mark-paid', [AdminOrderController::class, 'markPaid'])->name('orders.mark-paid');
    Route::post('/orders/{order}/provision', [AdminOrderController::class, 'provision'])->name('orders.provision');
    Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('orders.cancel');

    // Devices & allocation
    Route::get('/devices', [AdminDeviceController::class, 'index'])->name('devices.index');
    Route::post('/devices/import', [AdminDeviceController::class, 'import'])->name('devices.import');
    Route::post('/devices/{device}/allocate', [AdminDeviceController::class, 'allocate'])->name('devices.allocate');
    Route::post('/devices/{device}/deallocate', [AdminDeviceController::class, 'deallocate'])->name('devices.deallocate');
    Route::patch('/devices/{device}', [AdminDeviceController::class, 'update'])->name('devices.update');
    Route::delete('/devices/{device}', [AdminDeviceController::class, 'destroy'])->name('devices.destroy');

    // Users
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

    // Proxies
    Route::get('/proxies', [AdminProxyController::class, 'index'])->name('proxies.index');
    Route::post('/proxies/buy', [AdminProxyController::class, 'buy'])->name('proxies.buy');
    Route::post('/proxies/attach', [AdminProxyController::class, 'attach'])->name('proxies.attach');
    Route::delete('/proxies', [AdminProxyController::class, 'destroy'])->name('proxies.destroy');

    // Live chat transcripts
    Route::get('/chat', [AdminChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{conversation}', [AdminChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{conversation}/reply', [AdminChatController::class, 'reply'])->name('chat.reply');
    Route::post('/chat/{conversation}/handling', [AdminChatController::class, 'handling'])->name('chat.handling');
    Route::delete('/chat/{conversation}', [AdminChatController::class, 'destroy'])->name('chat.destroy');

    // API diagnostics
    Route::get('/diagnostics', [AdminDiagnosticsController::class, 'index'])->name('diagnostics.index');

    // Settings
    Route::get('/settings/{tab?}', [AdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings/site', [AdminSettingsController::class, 'updateSite'])->name('settings.site');
    Route::patch('/settings/payments', [AdminSettingsController::class, 'updatePayments'])->name('settings.payments');
    Route::patch('/settings/vmos', [AdminSettingsController::class, 'updateVmos'])->name('settings.vmos');
    Route::patch('/settings/mail', [AdminSettingsController::class, 'updateMail'])->name('settings.mail');
    Route::patch('/settings/assistant', [AdminSettingsController::class, 'updateAssistant'])->name('settings.assistant');
    Route::post('/settings/test-assistant', [AdminSettingsController::class, 'testAssistant'])->name('settings.test-assistant');
    Route::post('/settings/test-vmos', [AdminSettingsController::class, 'testVmos'])->name('settings.test-vmos');
    Route::post('/settings/test-mail', [AdminSettingsController::class, 'testMail'])->name('settings.test-mail');
});

require __DIR__.'/auth.php';
