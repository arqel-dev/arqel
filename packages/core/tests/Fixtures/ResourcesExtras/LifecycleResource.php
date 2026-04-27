<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\ResourcesExtras;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\Post;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource that records every lifecycle hook invocation in order so tests
 * can assert that the controller (and any future caller) drives them
 * through the documented sequence.
 */
final class LifecycleResource extends Resource
{
    public static string $model = Post::class;

    /** @var array<int, string> */
    public array $calls = [];

    public function fields(): array
    {
        return [];
    }

    public function runCreatePipeline(array $data, Model $record): void
    {
        $data = $this->beforeCreate($data);
        $data = $this->beforeSave($record, $data);
        $this->afterCreate($record);
        $this->afterSave($record);
    }

    public function runUpdatePipeline(Model $record, array $data): void
    {
        $data = $this->beforeUpdate($record, $data);
        $data = $this->beforeSave($record, $data);
        $this->afterUpdate($record);
        $this->afterSave($record);
    }

    protected function beforeCreate(array $data): array
    {
        $this->calls[] = 'beforeCreate';

        return $data;
    }

    protected function afterCreate(Model $record): void
    {
        $this->calls[] = 'afterCreate';
    }

    protected function beforeUpdate(Model $record, array $data): array
    {
        $this->calls[] = 'beforeUpdate';

        return $data;
    }

    protected function afterUpdate(Model $record): void
    {
        $this->calls[] = 'afterUpdate';
    }

    protected function beforeSave(Model $record, array $data): array
    {
        $this->calls[] = 'beforeSave';

        return $data;
    }

    protected function afterSave(Model $record): void
    {
        $this->calls[] = 'afterSave';
    }
}
