<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Arqel\Marketplace\Contracts\PaymentGateway;
use Arqel\Marketplace\Events\PluginPurchased;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use Arqel\Marketplace\Services\LicenseKeyGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checkout flow do marketplace público (MKTPLC-004-checkout).
 *
 * 4 actions:
 * - GET  /checkout/{slug}            → start
 * - POST /checkout/{slug}/initiate   → initiate (cria sessão + redirect externo)
 * - GET  /checkout/{slug}/success    → success (verify + license key)
 * - GET  /checkout/{slug}/cancel     → cancel
 */
final class CheckoutController
{
    public function start(Request $request, string $slug): Response
    {
        $plugin = Plugin::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        if (! $plugin->isPremium()) {
            throw ValidationException::withMessages([
                'plugin' => 'This plugin is free; checkout is not required.',
            ]);
        }

        $userId = $this->userId($request);

        $existing = PluginPurchase::query()
            ->where('plugin_id', $plugin->id)
            ->where('buyer_user_id', $userId)
            ->where('status', 'completed')
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'plugin' => 'You already own this plugin.',
            ]);
        }

        $feeEstimate = (int) round(
            $plugin->price_cents * ((100 - $plugin->revenue_share_percent) / 100),
        );

        return Inertia::render('Marketplace/Checkout', [
            'plugin' => $plugin,
            'summary' => [
                'price_cents' => $plugin->price_cents,
                'currency' => $plugin->currency,
                'fee_estimate_cents' => $feeEstimate,
                'total_cents' => $plugin->price_cents,
            ],
        ]);
    }

    public function initiate(Request $request, PaymentGateway $gateway, string $slug): RedirectResponse
    {
        $plugin = Plugin::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        if (! $plugin->isPremium()) {
            throw ValidationException::withMessages([
                'plugin' => 'This plugin is free; checkout is not required.',
            ]);
        }

        $userId = $this->userId($request);

        $session = $gateway->createCheckoutSession($plugin, $userId);

        /** @var PluginPurchase|null $existingPending */
        $existingPending = PluginPurchase::query()
            ->where('plugin_id', $plugin->id)
            ->where('buyer_user_id', $userId)
            ->where('status', 'pending')
            ->first();

        if ($existingPending instanceof PluginPurchase) {
            $existingPending->update(['payment_id' => $session->sessionId]);
        } else {
            PluginPurchase::query()->create([
                'plugin_id' => $plugin->id,
                'buyer_user_id' => $userId,
                'license_key' => 'PENDING-'.bin2hex(random_bytes(8)),
                'amount_cents' => $plugin->price_cents,
                'currency' => $plugin->currency,
                'payment_id' => $session->sessionId,
                'status' => 'pending',
            ]);
        }

        return new RedirectResponse($session->url);
    }

    public function success(Request $request, PaymentGateway $gateway, LicenseKeyGenerator $generator, string $slug): Response
    {
        $plugin = Plugin::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $userId = $this->userId($request);

        $sessionId = $request->query('session_id');
        if (! is_string($sessionId) || $sessionId === '') {
            throw ValidationException::withMessages([
                'session_id' => 'session_id is required.',
            ]);
        }

        /** @var PluginPurchase|null $purchase */
        $purchase = PluginPurchase::query()
            ->where('plugin_id', $plugin->id)
            ->where('buyer_user_id', $userId)
            ->where('payment_id', $sessionId)
            ->first();

        if (! $purchase instanceof PluginPurchase) {
            throw ValidationException::withMessages([
                'session_id' => 'Purchase not found for this session.',
            ]);
        }

        if ($purchase->status !== 'completed') {
            $result = $gateway->verifyPayment($sessionId);

            if ($result->status !== 'completed') {
                throw ValidationException::withMessages([
                    'session_id' => 'Payment is not completed yet.',
                ]);
            }

            $purchase->update([
                'status' => 'completed',
                'license_key' => $generator->generate(),
                'purchased_at' => now(),
            ]);

            PluginPurchased::dispatch($plugin, $purchase->fresh() ?? $purchase);
        }

        return Inertia::render('Marketplace/CheckoutSuccess', [
            'plugin' => $plugin,
            'license_key' => $purchase->license_key,
            'download_url' => '/plugins/'.$plugin->slug.'/download',
        ]);
    }

    public function cancel(string $slug): Response
    {
        $plugin = Plugin::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('Marketplace/CheckoutCancelled', [
            'plugin' => $plugin,
        ]);
    }

    private function userId(Request $request): int
    {
        $user = $request->user() ?? Auth::user();

        if ($user === null) {
            abort(401, 'Authentication required.');
        }

        $id = $user->getAuthIdentifier();
        $userId = is_numeric($id) ? (int) $id : 0;

        if ($userId === 0) {
            abort(401, 'Authentication required.');
        }

        return $userId;
    }
}
