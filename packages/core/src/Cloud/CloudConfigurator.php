<?php

declare(strict_types=1);

namespace Arqel\Core\Cloud;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Apply Laravel Cloud-friendly defaults at runtime.
 *
 * The configurator is intentionally **defensive**:
 *
 *   - It is a no-op when the {@see CloudDetector} reports the host is
 *     not Laravel Cloud, or when auto-configure is disabled via
 *     `arqel.cloud.auto_configure=false`.
 *   - Each driver swap is wrapped in `try/catch`; a failure in one key
 *     never blocks the rest. Failures are logged at `warning` level.
 *   - Existing non-default values are respected — for example, the
 *     filesystem default is only swapped to `s3` when it is still on
 *     `local`. Apps that have already opted into another driver are
 *     left alone.
 *
 * Implements LCLOUD-002 from PLANNING/11-fase-4-ecossistema.md.
 */
final readonly class CloudConfigurator
{
    public function __construct(private CloudDetector $detector) {}

    /**
     * Apply the cloud defaults. Returns the list of config keys that
     * were actually modified, in declaration order. Useful for the
     * `arqel:cloud:info` command and for `Doctor` checks.
     *
     * @return list<string>
     */
    public function configure(): array
    {
        if (! $this->detector->isLaravelCloud()) {
            return [];
        }

        if (! $this->detector->autoConfigureEnabled()) {
            return [];
        }

        $changed = [];

        $this->maybeSet(
            'filesystems.default',
            fn (mixed $current): bool => $current === 'local' || $current === null,
            's3',
            $changed,
        );

        $this->maybeSet(
            'cache.default',
            fn (mixed $current): bool => $current === 'array' || $current === 'file' || $current === null,
            'redis',
            $changed,
        );

        $this->maybeSet(
            'queue.default',
            fn (mixed $current): bool => $current === 'sync' || $current === null,
            'redis',
            $changed,
        );

        $this->maybeSet(
            'session.driver',
            fn (mixed $current): bool => $current === 'file' || $current === null,
            'redis',
            $changed,
        );

        if (getenv('REVERB_HOST') !== false) {
            $this->maybeSet(
                'broadcasting.default',
                static fn (mixed $current): bool => $current !== 'reverb',
                'reverb',
                $changed,
            );
        }

        $this->maybeSet(
            'logging.default',
            static fn (mixed $current): bool => $current !== 'stderr',
            'stderr',
            $changed,
        );

        return $changed;
    }

    /**
     * Conditionally rewrite `$key` to `$value` when the predicate
     * matches the current value. Failures are logged but never thrown.
     *
     * @param  callable(mixed): bool  $predicate
     * @param  list<string>  $changed
     */
    private function maybeSet(string $key, callable $predicate, string $value, array &$changed): void
    {
        try {
            $current = config($key);

            if (! $predicate($current)) {
                return;
            }

            config([$key => $value]);
            $changed[] = $key;
        } catch (Throwable $e) {
            try {
                Log::warning('Arqel Cloud auto-configure failed for config key', [
                    'key' => $key,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            } catch (Throwable) {
                // Logger may not be bound during very early boot.
            }
        }
    }
}
