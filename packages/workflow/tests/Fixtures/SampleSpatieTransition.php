<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

use Arqel\Workflow\Concerns\RecordsStateTransition;
use Illuminate\Database\Eloquent\Model;

/**
 * Mimics a spatie/laravel-model-states `Transition` class that opts into the
 * `RecordsStateTransition` trait to emit the canonical event from within its
 * own `handle()` body.
 */
final class SampleSpatieTransition
{
    use RecordsStateTransition;

    /**
     * @param array<string, mixed> $context
     */
    public function fire(Model $record, string $from, string $to, array $context = []): void
    {
        $this->recordTransition($record, $from, $to, $context);
    }
}
