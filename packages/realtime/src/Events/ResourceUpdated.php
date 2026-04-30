<?php

declare(strict_types=1);

namespace Arqel\Realtime\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * Broadcasts when a Resource record is updated.
 *
 * Two private channels per dispatch:
 *
 * - `arqel.{slug}` — list/index pages listening for any update on the
 *   Resource type (used to refresh tables / row counts).
 * - `arqel.{slug}.{id}` — detail pages listening for a single record.
 *
 * The slug is resolved by calling `{$resourceClass}::getSlug()`. We call
 * it defensively: if the class doesn't expose `getSlug()` (e.g. consumer
 * passed a non-Resource FQCN by mistake) or the call throws, we fall
 * back to a kebab-cased basename of the class. This keeps the event
 * dispatch resilient even when Resource bootstrapping is partial — the
 * subscriber side just receives a slightly different channel name.
 *
 * If `$record->id` is null (record not yet persisted), the per-record
 * channel is omitted; only the list channel is broadcast on. This makes
 * the event safe to fire from `beforeSave`-style hooks too.
 */
final class ResourceUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $resourceClass,
        public readonly Model $record,
        public readonly ?int $updatedByUserId = null,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $slug = $this->resolveSlug();

        $channels = [new PrivateChannel("arqel.{$slug}")];

        $id = $this->record->getKey();
        if ($id !== null) {
            /** @var int|string $id */
            $channels[] = new PrivateChannel("arqel.{$slug}.{$id}");
        }

        return $channels;
    }

    /**
     * @return array{id: int|string|null, updatedByUserId: int|null, updatedAt: mixed}
     */
    public function broadcastWith(): array
    {
        /** @var int|string|null $id */
        $id = $this->record->getKey();

        return [
            'id' => $id,
            'updatedByUserId' => $this->updatedByUserId,
            'updatedAt' => $this->record->getAttribute('updated_at'),
        ];
    }

    private function resolveSlug(): string
    {
        try {
            if (method_exists($this->resourceClass, 'getSlug')) {
                /** @var mixed $slug */
                $slug = $this->resourceClass::getSlug();
                if (is_string($slug) && $slug !== '') {
                    return $slug;
                }
            }
        } catch (Throwable) {
            // Fall through to the basename-based fallback below.
        }

        return Str::of(class_basename($this->resourceClass))
            ->beforeLast('Resource')
            ->snake('-')
            ->plural()
            ->toString();
    }
}
