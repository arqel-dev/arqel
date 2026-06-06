<?php

declare(strict_types=1);

namespace Arqel\Realtime\Presence;

use Arqel\Realtime\Exceptions\RealtimeException;

/**
 * Resolves the presence channel name for a given Resource record.
 *
 * The channel pattern is configurable via
 * `arqel-realtime.presence.channel_pattern` and supports the
 * `{resource}` and `{recordId}` placeholders. Presence can be
 * globally disabled via `arqel-realtime.presence.enabled`; calling
 * the resolver while disabled is treated as a programmer error and
 * raises {@see RealtimeException}.
 */
final readonly class PresenceChannelResolver
{
    /**
     * The default presence channel pattern, used when
     * `arqel-realtime.presence.channel_pattern` is unset or invalid. Must stay
     * in sync with the default declared in `config/arqel-realtime.php`.
     */
    public const string DEFAULT_PATTERN = 'arqel.presence.{resource}.{recordId}';

    /**
     * Read the configured presence channel pattern (placeholder form).
     *
     * This is the single source of truth shared by {@see self::forResource()}
     * (which a client uses to build the channel it subscribes to) and
     * `routes/channels.php` (which registers the broadcast authorization
     * callback). Both sides MUST derive the pattern from here so a custom
     * `arqel-realtime.presence.channel_pattern` keeps the subscribed channel
     * and the authorized channel in lockstep (issue #130).
     *
     * A custom pattern must keep the `{resource}` and `{recordId}` placeholder
     * tokens: Laravel binds presence route parameters positionally by name, so
     * the registered pattern's placeholders feed the channel callback's
     * arguments.
     */
    public static function pattern(): string
    {
        $pattern = config(
            'arqel-realtime.presence.channel_pattern',
            self::DEFAULT_PATTERN,
        );

        if (! is_string($pattern) || $pattern === '') {
            return self::DEFAULT_PATTERN;
        }

        return $pattern;
    }

    /**
     * Resolve the presence channel name for the given Resource slug + record id.
     *
     * @param string $slug Resource slug (e.g., `posts`).
     * @param int|string $recordId Primary key of the record being viewed.
     *
     * @throws RealtimeException When presence is globally disabled.
     */
    public static function forResource(string $slug, int|string $recordId): string
    {
        $enabled = config('arqel-realtime.presence.enabled', true);

        if ($enabled === false) {
            throw new RealtimeException(
                'Arqel realtime presence is disabled (arqel-realtime.presence.enabled = false).',
            );
        }

        return strtr(self::pattern(), [
            '{resource}' => $slug,
            '{recordId}' => (string) $recordId,
        ]);
    }
}
