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

        $pattern = config(
            'arqel-realtime.presence.channel_pattern',
            'arqel.presence.{resource}.{recordId}',
        );

        if (! is_string($pattern) || $pattern === '') {
            $pattern = 'arqel.presence.{resource}.{recordId}';
        }

        return strtr($pattern, [
            '{resource}' => $slug,
            '{recordId}' => (string) $recordId,
        ]);
    }
}
