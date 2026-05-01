<?php

declare(strict_types=1);

namespace Arqel\Core\Cloud;

/**
 * Detect whether the current process is running on Laravel Cloud.
 *
 * The detection uses three independent, defensive signals so a single
 * missing variable never produces a false negative:
 *
 *   1. `LARAVEL_CLOUD` env var (the canonical Laravel Cloud marker).
 *   2. `CLOUD_ENVIRONMENT` env var (legacy / pre-release marker).
 *   3. The presence of the `/var/run/laravel-cloud` sentinel file
 *      (set on managed runtime images).
 *
 * Implements LCLOUD-002 from PLANNING/11-fase-4-ecossistema.md.
 */
/**
 * Note: this class is intentionally not `final` so test suites and
 * downstream apps can substitute it via a binding override or a
 * Mockery double. All members are otherwise read-only.
 */
class CloudDetector
{
    public function __construct() {}

    /**
     * Returns `true` when at least one Laravel Cloud signal is present.
     */
    public function isLaravelCloud(): bool
    {
        $env = env('LARAVEL_CLOUD');
        if ($env === true || $env === '1' || $env === 'true') {
            return true;
        }

        if (getenv('CLOUD_ENVIRONMENT') !== false) {
            return true;
        }

        if (@is_file('/var/run/laravel-cloud')) {
            return true;
        }

        return false;
    }

    /**
     * Whether the runtime auto-configure is enabled. Defaults to `true`
     * so opt-out is explicit (`ARQEL_CLOUD_AUTO_CONFIGURE=false`).
     */
    public function autoConfigureEnabled(): bool
    {
        $value = config('arqel.cloud.auto_configure', true);

        return (bool) $value;
    }

    /**
     * Best-effort name for the detected hosting platform. Returns
     * `'laravel-cloud'` when any cloud signal is present, otherwise
     * `'unknown'` — never throws.
     */
    public function description(): string
    {
        return $this->isLaravelCloud() ? 'laravel-cloud' : 'unknown';
    }
}
