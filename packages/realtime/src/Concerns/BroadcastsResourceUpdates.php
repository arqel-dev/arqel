<?php

declare(strict_types=1);

namespace Arqel\Realtime\Concerns;

use Arqel\Realtime\Events\ResourceUpdated;
use Illuminate\Database\Eloquent\Model;

/**
 * Drop-in trait for `Arqel\Core\Resources\Resource` subclasses that wires
 * the `afterUpdate` lifecycle hook to dispatch a {@see ResourceUpdated}
 * broadcast event.
 *
 * We intentionally provide the wiring as a trait instead of patching the
 * core `Resource` base class — `arqel/core` and `arqel/realtime` ship as
 * independent packages and adding a hard hook to core would push every
 * consumer into the broadcasting stack whether they use it or not.
 *
 * Usage:
 *
 * ```php
 * use Arqel\Core\Resources\Resource;
 * use Arqel\Realtime\Concerns\BroadcastsResourceUpdates;
 *
 * final class PostResource extends Resource
 * {
 *     use BroadcastsResourceUpdates;
 *
 *     // ... fields, table, form, etc.
 * }
 * ```
 *
 * The trait honours the `arqel-realtime.auto_dispatch.resource_updated`
 * config flag — set it to `false` to short-circuit dispatch globally
 * (useful in tests or batch imports).
 *
 * Subclasses that already define their own `afterUpdate` can call
 * `parent::afterUpdate($record)` from their override; the trait method
 * is reachable via the parent chain because the trait is composed into
 * the user-land Resource subclass.
 */
trait BroadcastsResourceUpdates
{
    protected function afterUpdate(Model $record): void
    {
        if (! $this->shouldBroadcastResourceUpdated()) {
            return;
        }

        ResourceUpdated::dispatch(
            static::class,
            $record,
            $this->resolveBroadcastingUserId(),
        );
    }

    protected function shouldBroadcastResourceUpdated(): bool
    {
        $config = function_exists('config') ? config('arqel-realtime.auto_dispatch.resource_updated') : null;

        return $config === null || (bool) $config;
    }

    protected function resolveBroadcastingUserId(): ?int
    {
        if (! function_exists('auth')) {
            return null;
        }

        /** @var mixed $id */
        $id = auth()->id();

        return is_int($id) ? $id : null;
    }
}
