<?php

declare(strict_types=1);

use App\Http\Controllers\BrowseController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PluginDetailController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');
Route::get('/browse', BrowseController::class)->name('browse');
Route::get('/plugins/{slug}', PluginDetailController::class)->name('plugin.detail');

Route::middleware(['auth'])->group(static function (): void {
    Route::get('/checkout/{slug}', [CheckoutController::class, 'start'])->name('checkout.start');
    Route::post('/checkout/{slug}/initiate', [CheckoutController::class, 'initiate'])->name('checkout.initiate');
    Route::get('/checkout/{slug}/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/{slug}/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
});
