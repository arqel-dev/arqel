<?php

declare(strict_types=1);

namespace Arqel\Table\Filters;

use Arqel\Table\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * First-class soft-delete (trashed) filter.
 *
 * Mirrors Filament's TrashedFilter: a three-state select that toggles
 * the SoftDeletes global scope on a Resource's query without forcing
 * the app to hand-roll a SelectFilter + raw `onlyTrashed()` closure.
 *
 * States:
 *  - `without` (default): the normal global scope — trashed rows hidden.
 *  - `with`:              `withTrashed()` — active + soft-deleted rows.
 *  - `only`:              `onlyTrashed()` — only soft-deleted rows.
 *
 * The trashed logic is built in; no `apply()` closure is required.
 * Applying the filter to a model that is NOT SoftDeletes is a harmless
 * no-op (the query is returned untouched) rather than a 500.
 */
final class TrashedFilter extends Filter
{
    public const string STATE_WITHOUT = 'without';

    public const string STATE_WITH = 'with';

    public const string STATE_ONLY = 'only';

    /**
     * Serialised as `select` (not `trashed`) on purpose: the React
     * `FilterControl` switch (packages-js/ui TableFilters.tsx) renders
     * by discriminator and only has a `case 'select'`. Our `props` are
     * already select-shaped (`options:[{value,label}]`), so the existing
     * select control renders the 3 trashed states with no React change.
     * The soft-delete `apply()` logic below is unaffected by the type.
     */
    protected string $type = 'select';

    protected ?string $withoutLabel = null;

    protected ?string $withLabel = null;

    protected ?string $onlyLabel = null;

    public static function make(string $name = 'trashed'): static
    {
        $filter = new self($name);
        $filter->label('Trashed');
        $filter->default(self::STATE_WITHOUT);

        return $filter;
    }

    public function withoutLabel(string $label): static
    {
        $this->withoutLabel = $label;

        return $this;
    }

    public function withLabel(string $label): static
    {
        $this->withLabel = $label;

        return $this;
    }

    public function onlyLabel(string $label): static
    {
        $this->onlyLabel = $label;

        return $this;
    }

    /**
     * @param Builder<Model> $query
     *
     * @return Builder<Model>
     */
    protected function applyDefault(Builder $query, mixed $value): Builder
    {
        if (! $this->modelUsesSoftDeletes($query->getModel())) {
            return $query;
        }

        // `withTrashed()` / `onlyTrashed()` / `withoutTrashed()` are
        // macro'd onto the Builder by the SoftDeletes scope, so we
        // dispatch by a resolved method name (same approach as
        // ScopeFilter) to keep the call type-safe under level max.
        $method = $this->resolveScopeMethod($value);

        /** @var Builder<Model> $result */
        $result = $query->{$method}();

        return $result;
    }

    private function resolveScopeMethod(mixed $value): string
    {
        if ($value === self::STATE_WITH) {
            return 'withTrashed';
        }

        if ($value === self::STATE_ONLY) {
            return 'onlyTrashed';
        }

        return 'withoutTrashed';
    }

    private function modelUsesSoftDeletes(Model $model): bool
    {
        return in_array(
            SoftDeletes::class,
            class_uses_recursive($model),
            true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'options' => [
                ['value' => self::STATE_WITHOUT, 'label' => $this->withoutLabel ?? 'Without deleted'],
                ['value' => self::STATE_WITH, 'label' => $this->withLabel ?? 'With deleted'],
                ['value' => self::STATE_ONLY, 'label' => $this->onlyLabel ?? 'Only deleted'],
            ],
        ];
    }
}
