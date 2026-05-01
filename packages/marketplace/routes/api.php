<?php

declare(strict_types=1);

use Arqel\Marketplace\Http\Controllers\PluginAdminListController;
use Arqel\Marketplace\Http\Controllers\PluginAdminReviewController;
use Arqel\Marketplace\Http\Controllers\PluginDetailController;
use Arqel\Marketplace\Http\Controllers\PluginListController;
use Arqel\Marketplace\Http\Controllers\PluginReviewController;
use Arqel\Marketplace\Http\Controllers\PluginReviewListController;
use Arqel\Marketplace\Http\Controllers\PluginReviewModerationController;
use Arqel\Marketplace\Http\Controllers\PluginReviewVoteController;
use Arqel\Marketplace\Http\Controllers\PluginSubmissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes do pacote `arqel/marketplace`
|--------------------------------------------------------------------------
|
| Endpoints REST públicos para o marketplace de plugins. O prefixo é
| configurável em `config('arqel-marketplace.route_prefix')`. Quando
| `config('arqel-marketplace.enabled')` for `false`, nenhuma rota é
| registrada.
*/

if (config('arqel-marketplace.enabled', true) !== true) {
    return;
}

/** @var mixed $rawPrefix */
$rawPrefix = config('arqel-marketplace.route_prefix', 'api/marketplace');
$prefix = is_string($rawPrefix) ? $rawPrefix : 'api/marketplace';

$reviewMiddleware = ['api'];
if (in_array('auth:sanctum', array_keys(config('auth.guards', [])), true)) {
    $reviewMiddleware[] = 'auth:sanctum';
} else {
    $reviewMiddleware[] = 'auth';
}

Route::middleware('api')->prefix($prefix)->group(static function (): void {
    Route::get('plugins', PluginListController::class)
        ->name('arqel.marketplace.plugins.index');

    Route::get('plugins/{slug}', PluginDetailController::class)
        ->name('arqel.marketplace.plugins.show');

    Route::get('plugins/{slug}/reviews', PluginReviewListController::class)
        ->name('arqel.marketplace.plugins.reviews.index');
});

Route::middleware($reviewMiddleware)->prefix($prefix)->group(static function (): void {
    Route::post('plugins/{slug}/reviews', PluginReviewController::class)
        ->name('arqel.marketplace.plugins.reviews.store');

    Route::post('plugins/submit', PluginSubmissionController::class)
        ->name('arqel.marketplace.submit');

    Route::get('admin/plugins', PluginAdminListController::class)
        ->name('arqel.marketplace.admin.list');

    Route::post('admin/plugins/{slug}/review', PluginAdminReviewController::class)
        ->name('arqel.marketplace.admin.review');

    Route::post('plugins/{slug}/reviews/{reviewId}/vote', [PluginReviewVoteController::class, 'store'])
        ->whereNumber('reviewId')
        ->name('arqel.marketplace.plugins.reviews.vote.store');

    Route::delete('plugins/{slug}/reviews/{reviewId}/vote', [PluginReviewVoteController::class, 'destroy'])
        ->whereNumber('reviewId')
        ->name('arqel.marketplace.plugins.reviews.vote.destroy');

    Route::get('admin/reviews', [PluginReviewModerationController::class, 'index'])
        ->name('arqel.marketplace.admin.reviews.index');

    Route::post('admin/reviews/{reviewId}/moderate', [PluginReviewModerationController::class, 'moderate'])
        ->whereNumber('reviewId')
        ->name('arqel.marketplace.admin.reviews.moderate');
});
