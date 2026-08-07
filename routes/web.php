<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DeviceController as AdminDeviceController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SkuController as AdminSkuController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\CloudInstanceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');

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
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Plans & pricing
    Route::get('/skus', [AdminSkuController::class, 'index'])->name('skus.index');
    Route::patch('/skus/{sku}', [AdminSkuController::class, 'update'])->name('skus.update');
    Route::post('/skus/sync', [AdminSkuController::class, 'sync'])->name('skus.sync');
    Route::post('/skus/bulk-markup', [AdminSkuController::class, 'bulkMarkup'])->name('skus.bulk-markup');

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

    // Settings
    Route::get('/settings/{tab?}', [AdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings/site', [AdminSettingsController::class, 'updateSite'])->name('settings.site');
    Route::patch('/settings/payments', [AdminSettingsController::class, 'updatePayments'])->name('settings.payments');
    Route::patch('/settings/vmos', [AdminSettingsController::class, 'updateVmos'])->name('settings.vmos');
    Route::patch('/settings/mail', [AdminSettingsController::class, 'updateMail'])->name('settings.mail');
    Route::post('/settings/test-vmos', [AdminSettingsController::class, 'testVmos'])->name('settings.test-vmos');
    Route::post('/settings/test-mail', [AdminSettingsController::class, 'testMail'])->name('settings.test-mail');
});

require __DIR__.'/auth.php';
