<?php

use App\Http\Controllers\Admin\SkuController as AdminSkuController;
use App\Http\Controllers\CloudInstanceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PlanController::class, 'index'])->name('home');
Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');

Route::get('/dashboard', function () {
    return redirect()->route('instances.index');
})->middleware(['auth', 'verified'])->name('dashboard');

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

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/skus', [AdminSkuController::class, 'index'])->name('skus.index');
        Route::patch('/skus/{sku}', [AdminSkuController::class, 'update'])->name('skus.update');
    });
});

require __DIR__.'/auth.php';
