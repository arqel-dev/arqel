<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Services;

use Arqel\Marketplace\Models\PluginPurchase;

/**
 * Geração e verificação de license keys para plugins premium (MKTPLC-008).
 *
 * Formato: `ARQ-XXXX-XXXX-XXXX-XXXX` (4 grupos hex de 4 chars cada).
 * O prefixo `ARQ` é decorativo, mas exigido na verificação de formato.
 */
final readonly class LicenseKeyGenerator
{
    private const PATTERN = '/^ARQ-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}$/';

    public function generate(): string
    {
        $bytes = random_bytes(8);
        $hex = bin2hex($bytes);

        $groups = [
            substr($hex, 0, 4),
            substr($hex, 4, 4),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
        ];

        return 'ARQ-'.implode('-', $groups);
    }

    public function verify(string $key, PluginPurchase $purchase): bool
    {
        if (preg_match(self::PATTERN, $key) !== 1) {
            return false;
        }

        if ($purchase->status !== 'completed') {
            return false;
        }

        return hash_equals($purchase->license_key, $key);
    }
}
