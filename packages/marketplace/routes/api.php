<?php

declare(strict_types=1);

use Arqel\Marketplace\Http\Controllers\PluginDetailController;
use Arqel\Marketplace\Http\Controllers\PluginListController;
use Arqel\Marketplace\Http\Controllers\PluginReviewController;
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
});

Route::middleware($reviewMiddleware)->prefix($prefix)->group(static function (): void {
    Route::post('plugins/{slug}/reviews', PluginReviewController::class)
        ->name('arqel.marketplace.plugins.reviews.store');
});
