<?php

declare(strict_types=1);

use Arqel\Ai\Http\Controllers\AiAnalyzeImageController;
use Arqel\Ai\Http\Controllers\AiClassifyController;
use Arqel\Ai\Http\Controllers\AiExtractController;
use Arqel\Ai\Http\Controllers\AiGenerateController;
use Arqel\Ai\Http\Controllers\AiTranslateController;
use Illuminate\Support\Facades\Route;

Route::post('/admin/{resource}/fields/{field}/generate', AiGenerateController::class)
    ->middleware(['web', 'auth'])
    ->name('arqel.ai.generate');

Route::post('/admin/{resource}/fields/{field}/translate', AiTranslateController::class)
    ->middleware(['web', 'auth'])
    ->name('arqel.ai.translate');

Route::post('/admin/{resource}/fields/{field}/classify', AiClassifyController::class)
    ->middleware(['web', 'auth'])
    ->name('arqel.ai.classify');

Route::post('/admin/{resource}/fields/{field}/extract', AiExtractController::class)
    ->middleware(['web', 'auth'])
    ->name('arqel.ai.extract');

Route::post('/admin/{resource}/fields/{field}/analyze-image', AiAnalyzeImageController::class)
    ->middleware(['web', 'auth'])
    ->name('arqel.ai.analyzeImage');
