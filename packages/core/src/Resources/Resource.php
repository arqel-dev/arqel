<?php

declare(strict_types=1);

namespace Arqel\Core\Resources;

use Arqel\Core\Contracts\HasActions;
use Arqel\Core\Contracts\HasFields;
use Arqel\Core\Contracts\HasResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
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
        $label = static::$label ?? Str::of(class_basename(static::getModel()))
            ->snake(' ')
            ->title()
            ->toString();

        return static::localizeLabel($label);
    }

    public static function getPluralLabel(): string
    {
        $label = static::$pluralLabel ?? Str::of(static::getLabel())
            ->plural()
            ->toString();

        return static::localizeLabel($label);
    }

    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon;
    }

    public static function getNavigationGroup(): ?string
    {
        $group = static::$navigationGroup;

        return $group === null ? null : static::localizeLabel($group);
    }

    /**
     * Resolve a label through Laravel translation lazily so the active request
     * locale applies at serialization time. A label that is a translation key
     * renders in the current locale; a plain literal passes through unchanged
     * (Laravel __() returns the key when no translation exists). Falls back to
     * the raw literal when no translator is bound (e.g. unit context).
     */
    private static function localizeLabel(string $label): string
    {
        if (! app()->bound('translator')) {
            return $label;
        }

        $translated = trans($label);

        return is_string($translated) ? $translated : $label;
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
     * `arqel-dev/core` depend on `arqel-dev/table` — the controller duck-
     * types the result.
     */
    public function table(): mixed
    {
        return null;
    }

    /**
     * Optional Form schema for create/edit/show pages. When the
     * Resource declares one, the controller renders a layout-aware
     * payload (Section/Fieldset/Grid/Columns/Group/Tabs/Tab) instead
     * of just the flat field list from `fields()`.
     *
     * The return type is `mixed` (not `?Form`) for the same reason
     * as `table()` — `arqel-dev/core` cannot depend on `arqel-dev/form`.
     * The InertiaDataBuilder duck-types the result against
     * `getFields()`/`getSchema()`/`toArray()`.
     */
    public function form(): mixed
    {
        return null;
    }

    /**
     * The effective field list for this Resource: the form's fields when
     * a form() schema is declared, otherwise the flat fields(). This is
     * the single source both validation (rule extraction) and rendering
     * read, so a layout-aware form() does not require re-declaring every
     * field in fields().
     *
     * Only `getFields()` is required here; the layout-payload path in
     * InertiaDataBuilder additionally needs `toArray()`.
     *
     * When `$record` is supplied, layout-level visibility is honoured:
     * fields whose only guard is an enclosing hidden layout
     * (`canSee`/`visibleIf`) are pruned, so they never leak to render or
     * write (#115). On create the caller passes `null`.
     *
     * @return array<int, mixed>
     */
    public function effectiveFields(?Model $record = null): array
    {
        $form = $this->form();

        if (is_object($form) && method_exists($form, 'getFields')) {
            $fields = $form->getFields($record);

            if (is_array($fields)) {
                return array_values($fields);
            }
        }

        return $this->fields();
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

        $data = $this->storeUploadedFiles($data, $record);
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
        $data = $this->storeUploadedFiles($data, $record);
        $data = $this->beforeSave($record, $data);
        $data = $this->beforeUpdate($record, $data);

        $record->fill($data)->save();

        $this->afterUpdate($record);
        $this->afterSave($record);

        return $record;
    }

    /**
     * Persist any uploaded files submitted through the main form's
     * multipart body before `fill()` (#245). The stock ImageInput/
     * FileInput submit the raw `File`, so a file/image field's value
     * reaches the write pipeline as an `UploadedFile`; without this step
     * the column would be filled with the upload object (cast to its temp
     * path, which vanishes) and nothing would ever reach disk.
     *
     * Upload-capable fields are detected by duck-typing the
     * `storeUploadedFile()` marker, so `arqel-dev/core` stays decoupled
     * from `arqel-dev/fields` — the FileField owns the single store
     * implementation (disk/directory/hashName/visibility), shared with the
     * direct-upload `FieldUploadController`.
     *
     * Only an actual `UploadedFile` value is stored: a string (the
     * unchanged stored path re-submitted on edit) or null is left
     * untouched, so editing a record without re-uploading never wipes the
     * existing file.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function storeUploadedFiles(array $data, Model $record): array
    {
        foreach ($this->effectiveFields($record->exists ? $record : null) as $field) {
            if (! is_object($field) || ! method_exists($field, 'storeUploadedFile') || ! method_exists($field, 'getName')) {
                continue;
            }

            $name = $field->getName();

            if (! array_key_exists($name, $data) || ! $data[$name] instanceof UploadedFile) {
                continue;
            }

            $data[$name] = $field->storeUploadedFile($data[$name]);
        }

        return $data;
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
