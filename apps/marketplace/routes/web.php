<?php

declare(strict_types=1);

use App\Http\Controllers\BrowseController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PluginDetailController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');
Route::get('/browse', BrowseController::class)->name('browse');
Route::get('/plugins/{slug}', PluginDetailController::class)->name('plugin.detail');
