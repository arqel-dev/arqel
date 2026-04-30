<?php

declare(strict_types=1);

use Arqel\Ai\Http\Controllers\AiGenerateController;
use Illuminate\Support\Facades\Route;

Route::post('/admin/{resource}/fields/{field}/generate', AiGenerateController::class)
    ->middleware(['web', 'auth'])
    ->name('arqel.ai.generate');
