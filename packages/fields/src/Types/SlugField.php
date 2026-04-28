<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

/**
 * Slug input.
 *
 * `fromField('title')` declares the source attribute for live slug
 * generation in the React layer; the dependency wiring lives in
 * `HasDependencies` (FIELDS-017). Until then, the prop is exposed
 * verbatim in the type-specific props.
 *
 * `reservedSlugs([...])` blocks values that would clash with
 * application routes (e.g. `admin`, `api`). `unique($modelClass)`
 * declares an Eloquent uniqueness gate; the rule string is built
 * lazily so the controller can inject the current record id when
 * editing.
 */
final class SlugField extends TextField
{
    protected string $type = 'slug';

    protected string $component = 'SlugInput';

    protected ?string $fromField = null;

    protected string $separator = '-';

    /** @var array<int, string> */
    protected array $reservedSlugs = [];

    /** @var class-string|null */
    protected ?string $uniqueModel = null;

    protected ?string $uniqueColumn = null;

    public function fromField(string $field): static
    {
        $this->fromField = $field;

        return $this;
    }

    public function separator(string $separator): static
    {
        $this->separator = $separator;

        return $this;
    }

    /**
     * @param array<int, string> $slugs
     */
    public function reservedSlugs(array $slugs): static
    {
        $this->reservedSlugs = array_values($slugs);

        return $this;
    }

    /**
     * @param class-string $modelClass
     */
    public function unique(string $modelClass, ?string $column = null): static
    {
        $this->uniqueModel = $modelClass;
        $this->uniqueColumn = $column ?? $this->getName();

        return $this;
    }

    /** @return array<int, string> */
    public function getReservedSlugs(): array
    {
        return $this->reservedSlugs;
    }

    /** @return class-string|null */
    public function getUniqueModel(): ?string
    {
        return $this->uniqueModel;
    }

    public function getUniqueColumn(): ?string
    {
        return $this->uniqueColumn;
    }

    /**
     * @return array<int, string>
     */
    public function getDefaultRules(): array
    {
        $rules = ['string'];

        if ($this->reservedSlugs !== []) {
            $rules[] = 'not_in:'.implode(',', $this->reservedSlugs);
        }

        if ($this->uniqueModel !== null) {
            $table = $this->resolveTableName($this->uniqueModel);
            $rules[] = 'unique:'.$table.','.$this->uniqueColumn;
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            ...parent::getTypeSpecificProps(),
            'fromField' => $this->fromField,
            'separator' => $this->separator,
            'reservedSlugs' => $this->reservedSlugs !== [] ? $this->reservedSlugs : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param class-string $modelClass
     */
    protected function resolveTableName(string $modelClass): string
    {
        if (method_exists($modelClass, 'getTable')) {
            $instance = new $modelClass;
            $table = $instance->getTable();
            if (is_string($table) && $table !== '') {
                return $table;
            }
        }

        $basename = (string) (strrchr($modelClass, '\\') ?: $modelClass);
        $basename = ltrim($basename, '\\');

        return strtolower($basename).'s';
    }
}
