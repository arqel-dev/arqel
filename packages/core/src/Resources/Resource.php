<?php

declare(strict_types=1);

namespace Arqel\Core\Resources;

use Arqel\Core\Contracts\HasActions;
use Arqel\Core\Contracts\HasFields;
use Arqel\Core\Contracts\HasResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/**
 * Base class every user-defined Resource extends.
 *
 * Static metadata (`$model`, `$slug`, …) is read by the registry and
 * controllers without instantiating the class; per-record logic lives
 * on instance methods. Auto-derivation fills slug/label/plural-label
 * from the class name when the static fields are left null.
 *
 * Lifecycle hooks (`beforeCreate`/`afterCreate`/...) default to
 * no-ops so subclasses only override what they need. The "save"
 * variants run on both create and update so shared logic lives in
 * one place.
 */
abstract class Resource implements HasActions, HasFields, HasResource
{
    /**
     * Fully-qualified Eloquent model class managed by this Resource.
     *
     * Required: subclasses must declare it. Reading it before assignment
     * raises a {@see LogicException} from {@see static::getModel()} so
     * misconfigurations surface immediately.
     *
     * @var class-string<Model>
     */
    public static string $model;

    public static ?string $label = null;

    public static ?string $pluralLabel = null;

    public static ?string $slug = null;

    public static ?string $navigationIcon = null;

    public static ?string $navigationGroup = null;

    public static ?int $navigationSort = null;

    public static ?string $recordTitleAttribute = null;

    /**
     * @return class-string<Model>
     */
    public static function getModel(): string
    {
        if (! isset(static::$model)) {
            throw new LogicException(
                'Resource ['.static::class.'] must declare a public static string $model.',
            );
        }

        return static::$model;
    }

    public static function getSlug(): string
    {
        return static::$slug ?? Str::of(class_basename(static::class))
            ->beforeLast('Resource')
            ->snake('-')
            ->plural()
            ->toString();
    }

    public static function getLabel(): string
    {
        return static::$label ?? Str::of(class_basename(static::getModel()))
            ->snake(' ')
            ->title()
            ->toString();
    }

    public static function getPluralLabel(): string
    {
        return static::$pluralLabel ?? Str::of(static::getLabel())
            ->plural()
            ->toString();
    }

    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon;
    }

    public static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup;
    }

    public static function getNavigationSort(): ?int
    {
        return static::$navigationSort;
    }

    /**
     * @return array<int, mixed>
     */
    abstract public function fields(): array;

    /**
     * Return a custom query builder for the index page.
     *
     * Returning `null` lets the controller fall back to
     * `static::getModel()::query()`. Override to add scopes, eager
     * loads, or tenant filtering at the Resource level.
     */
    public function indexQuery(): mixed
    {
        return null;
    }

    /**
     * Return the `Arqel\Table\Table` that drives the index page.
     *
     * Returning `null` makes the controller fall back to a plain
     * paginated list of all model attributes. Subclasses should
     * override and return a fully-configured Table when they want
     * declarative columns/filters/actions.
     *
     * The return type is `mixed` (not `?Table`) to avoid making
     * `arqel/core` depend on `arqel/table` — the controller duck-
     * types the result.
     */
    public function table(): mixed
    {
        return null;
    }

    public function recordTitle(Model $record): string
    {
        $attribute = static::$recordTitleAttribute;

        if ($attribute !== null) {
            $value = $record->getAttribute($attribute);

            if (is_scalar($value)) {
                return (string) $value;
            }
        }

        $key = $record->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    public function recordSubtitle(Model $record): ?string
    {
        return null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function beforeCreate(array $data): array
    {
        return $data;
    }

    protected function afterCreate(Model $record): void {}

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function beforeUpdate(Model $record, array $data): array
    {
        return $data;
    }

    protected function afterUpdate(Model $record): void {}

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function beforeSave(Model $record, array $data): array
    {
        return $data;
    }

    protected function afterSave(Model $record): void {}

    protected function beforeDelete(Model $record): void {}

    protected function afterDelete(Model $record): void {}

    /**
     * Public orchestrator for resource creation. Runs through
     * `beforeSave` → `beforeCreate` → persist → `afterCreate` →
     * `afterSave`. Subclasses should override the hooks, not this
     * method. Returns the persisted record.
     *
     * @param array<string, mixed> $data
     */
    public function runCreate(array $data): Model
    {
        $modelClass = static::getModel();
        /** @var Model $record */
        $record = app($modelClass);

        $data = $this->beforeSave($record, $data);
        $data = $this->beforeCreate($data);

        $record->fill($data)->save();

        $this->afterCreate($record);
        $this->afterSave($record);

        return $record;
    }

    /**
     * Public orchestrator for resource update. Runs through
     * `beforeSave` → `beforeUpdate` → persist → `afterUpdate` →
     * `afterSave`.
     *
     * @param array<string, mixed> $data
     */
    public function runUpdate(Model $record, array $data): Model
    {
        $data = $this->beforeSave($record, $data);
        $data = $this->beforeUpdate($record, $data);

        $record->fill($data)->save();

        $this->afterUpdate($record);
        $this->afterSave($record);

        return $record;
    }

    /**
     * Public orchestrator for resource deletion. Returns true on
     * success — false when the underlying `delete()` call returns
     * a falsey value.
     */
    public function runDelete(Model $record): bool
    {
        $this->beforeDelete($record);

        $result = (bool) $record->delete();

        if ($result) {
            $this->afterDelete($record);
        }

        return $result;
    }
}
