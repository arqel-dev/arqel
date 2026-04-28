<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

use Arqel\Core\Contracts\HasResource;
use Arqel\Fields\Field;
use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * BelongsTo relationship picker.
 *
 * Configured via the `make()` factory rather than the constructor
 * because `Field::__construct` is final (FIELDS-002 design intent).
 * The related Resource class-string is captured eagerly so config
 * mistakes surface immediately; everything else (search route,
 * inline option creation, preload payload) is recorded as metadata
 * and resolved by the controller in CORE-006.
 */
final class BelongsToField extends Field
{
    protected string $type = 'belongsTo';

    protected string $component = 'BelongsToInput';

    /** @var class-string<HasResource> */
    protected string $relatedResource;

    protected string $relationshipName;

    /** @var array<int, string> */
    protected array $searchColumns = [];

    protected bool $preload = false;

    protected bool $searchable = true;

    protected ?Closure $optionLabel = null;

    protected ?Closure $relationQuery = null;

    /**
     * @param class-string<HasResource> $relatedResource
     */
    public static function make(string $name, string $relatedResource): self
    {
        $field = new self($name);
        $field->setRelatedResource($relatedResource);

        return $field;
    }

    /**
     * @param class-string<HasResource> $relatedResource
     */
    public function setRelatedResource(string $relatedResource): static
    {
        if (! is_subclass_of($relatedResource, HasResource::class)) {
            throw new InvalidArgumentException(
                'Related Resource ['.$relatedResource.'] must implement '.HasResource::class.'.',
            );
        }

        $this->relatedResource = $relatedResource;
        $this->relationshipName = Str::of($this->getName())->beforeLast('_id')->toString() ?: $this->getName();

        return $this;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function preload(bool $preload = true): static
    {
        $this->preload = $preload;

        return $this;
    }

    /**
     * @param array<int, string> $columns
     */
    public function searchColumns(array $columns): static
    {
        $this->searchColumns = array_values($columns);

        return $this;
    }

    public function optionLabel(Closure $callback): static
    {
        $this->optionLabel = $callback;

        return $this;
    }

    public function relationship(string $name, ?Closure $query = null): static
    {
        $this->relationshipName = $name;
        $this->relationQuery = $query;

        return $this;
    }

    /** @return class-string<HasResource> */
    public function getRelatedResource(): string
    {
        return $this->relatedResource;
    }

    public function getRelationshipName(): string
    {
        return $this->relationshipName;
    }

    /** @return array<int, string> */
    public function getSearchColumns(): array
    {
        return $this->searchColumns;
    }

    public function isPreloadEnabled(): bool
    {
        return $this->preload;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function getOptionLabel(): ?Closure
    {
        return $this->optionLabel;
    }

    public function getRelationQuery(): ?Closure
    {
        return $this->relationQuery;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'relatedResource' => $this->relatedResource,
            'relationship' => $this->relationshipName,
            'searchable' => $this->searchable,
            'searchColumns' => $this->searchColumns,
            'preload' => $this->preload,
            // Concrete `searchRoute`, `preloadedOptions`, `createRoute`,
            // and serialised `optionLabel` are filled in by the controller
            // (CORE-006) once the owner Resource and panel routing exist.
        ];
    }
}
