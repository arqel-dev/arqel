<?php

declare(strict_types=1);

namespace Arqel\Actions\Types;

use Arqel\Actions\Action;
use Illuminate\Support\Collection;

/**
 * Bulk action invoked over the Table's selection. The callback
 * receives a `Collection<int, Model>` per chunk; chunking is
 * automatic (default 100) so very large selections do not blow
 * memory or per-request timeouts.
 */
final class BulkAction extends Action
{
    public const int DEFAULT_CHUNK_SIZE = 100;

    protected string $type = 'bulk';

    protected bool $deselectAfter = true;

    protected int $chunkSize = self::DEFAULT_CHUNK_SIZE;

    public function deselectRecordsAfterCompletion(bool $deselect = true): static
    {
        $this->deselectAfter = $deselect;

        return $this;
    }

    public function chunkSize(int $size): static
    {
        $this->chunkSize = max(1, $size);

        return $this;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function shouldDeselectAfterCompletion(): bool
    {
        return $this->deselectAfter;
    }

    /**
     * Override execute to chunk the selection. `$record` is
     * expected to be a `Collection<int, Model>`; non-Collection
     * inputs delegate to the parent (single-record callback).
     */
    public function execute(mixed $record = null, array $data = []): mixed
    {
        if (! $record instanceof Collection || ! $this->hasCallback()) {
            return parent::execute($record, $data);
        }

        $results = [];
        foreach ($record->chunk($this->chunkSize) as $chunk) {
            $results[] = parent::execute($chunk, $data);
        }

        return $results;
    }
}
