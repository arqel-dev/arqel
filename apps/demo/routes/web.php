<?php

declare(strict_types=1);

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\PostAiSummaryController;
use App\Http\Controllers\PostCreateController;
use App\Http\Controllers\PostListController;
use App\Http\Controllers\PostTransitionController;
use App\Http\Controllers\TagClassifyController;
use App\Http\Controllers\TagListController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(static function (): void {
    Route::get('/', AdminDashboardController::class)->name('admin.dashboard');
    Route::get('/posts', PostListController::class)->name('admin.posts.index');
    Route::get('/posts/create', PostCreateController::class)->name('admin.posts.create');
    Route::post('/posts/ai/summary', PostAiSummaryController::class)->name('admin.posts.ai.summary');
    Route::post('/posts/{post}/transition', PostTransitionController::class)->name('admin.posts.transition');
    Route::get('/tags', TagListController::class)->name('admin.tags.index');
    Route::post('/tags/ai/classify', TagClassifyController::class)->name('admin.tags.ai.classify');
});
