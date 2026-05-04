<?php

declare(strict_types=1);

use Arqel\Marketplace\Http\Controllers\AdminRefundController;
use Arqel\Marketplace\Http\Controllers\CategoryListController;
use Arqel\Marketplace\Http\Controllers\FeaturedPluginsController;
use Arqel\Marketplace\Http\Controllers\MostPopularPluginsController;
use Arqel\Marketplace\Http\Controllers\NewPluginsController;
use Arqel\Marketplace\Http\Controllers\PluginAdminListController;
use Arqel\Marketplace\Http\Controllers\PluginAdminReviewController;
use Arqel\Marketplace\Http\Controllers\PluginDetailController;
use Arqel\Marketplace\Http\Controllers\PluginDownloadController;
use Arqel\Marketplace\Http\Controllers\PluginFeatureController;
use Arqel\Marketplace\Http\Controllers\PluginListController;
use Arqel\Marketplace\Http\Controllers\PluginPurchaseController;
use Arqel\Marketplace\Http\Controllers\PluginReviewController;
use Arqel\Marketplace\Http\Controllers\PluginReviewListController;
use Arqel\Marketplace\Http\Controllers\PluginReviewModerationController;
use Arqel\Marketplace\Http\Controllers\PluginReviewVoteController;
use Arqel\Marketplace\Http\Controllers\PluginsByCategoryController;
use Arqel\Marketplace\Http\Controllers\PluginSubmissionController;
use Arqel\Marketplace\Http\Controllers\PublisherPayoutsController;
use Arqel\Marketplace\Http\Controllers\SecurityScanListController;
use Arqel\Marketplace\Http\Controllers\TrendingPluginsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes do pacote `arqel-dev/marketplace`
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

    Route::get('categories', CategoryListController::class)
        ->name('arqel.marketplace.categories.index');

    Route::get('categories/{slug}/plugins', PluginsByCategoryController::class)
        ->name('arqel.marketplace.categories.plugins');

    Route::get('featured', FeaturedPluginsController::class)
        ->name('arqel.marketplace.featured');

    Route::get('trending', TrendingPluginsController::class)
        ->name('arqel.marketplace.trending');

    Route::get('new', NewPluginsController::class)
        ->name('arqel.marketplace.new');

    Route::get('popular', MostPopularPluginsController::class)
        ->name('arqel.marketplace.popular');
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

    Route::post('admin/plugins/{slug}/feature', PluginFeatureController::class)
        ->name('arqel.marketplace.admin.plugins.feature');

    Route::get('admin/security-scans', SecurityScanListController::class)
        ->name('arqel.marketplace.admin.security-scans.index');

    Route::post('plugins/{slug}/purchase', [PluginPurchaseController::class, 'initiate'])
        ->name('arqel.marketplace.plugins.purchase.initiate');

    Route::post('plugins/{slug}/purchase/confirm', [PluginPurchaseController::class, 'confirm'])
        ->name('arqel.marketplace.plugins.purchase.confirm');

    Route::get('plugins/{slug}/download', PluginDownloadController::class)
        ->name('arqel.marketplace.plugins.download');

    Route::get('publisher/payouts', PublisherPayoutsController::class)
        ->name('arqel.marketplace.publisher.payouts');

    Route::post('admin/plugins/{slug}/refund/{purchaseId}', AdminRefundController::class)
        ->whereNumber('purchaseId')
        ->name('arqel.marketplace.admin.plugins.refund');
});
