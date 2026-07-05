<?php

declare(strict_types=1);

namespace Arqel\Import;

use Illuminate\Database\Eloquent\Model;

/**
 * Base class a consumer app extends to declare an import.
 *
 * The child sets `$model`, declares `columns()`, and optionally
 * overrides `resolveRecord()` to upsert instead of insert.
 */
abstract class Importer
{
    /** @var class-string<Model> */
    public static string $model;

    /** @return array<int, ImportColumn> */
    abstract public function columns(): array;

    /**
     * Resolve the Eloquent model a validated row maps to.
     * Default: a fresh instance (insert). Override for upserts, e.g.
     * `return User::firstOrNew(['email' => $data['email']]);`.
     *
     * @param array<string, mixed> $data
     */
    public function resolveRecord(array $data): Model
    {
        $model = static::$model;

        return new $model;
    }

    /**
     * Validation rules keyed by column name, derived from columns().
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->columns() as $column) {
            $rules[$column->getName()] = $column->getRules();
        }

        return $rules;
    }
}
