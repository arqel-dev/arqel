<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

use Arqel\Core\Contracts\HasResource;
use Arqel\Fields\Field;
use InvalidArgumentException;

/**
 * HasMany relationship view (readonly in Phase 1).
 *
 * Renders an inline table of related records. Inline create/edit/
 * delete is intentionally out of scope for Phase 1 — the Repeater
 * pattern lands in Phase 2. `canAdd()`/`canEdit()` are accepted now
 * as forward-compatible flags so apps can declare intent without
 * breaking when the behaviour ships.
 */
final class HasManyField extends Field
{
    protected string $type = 'hasMany';

    protected string $component = 'HasManyTable';

    /** @var class-string<HasResource> */
    protected string $relatedResource;

    protected string $relationshipName;

    protected bool $canAdd = false;

    protected bool $canEdit = false;

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
        $this->relationshipName = $this->getName();

        return $this;
    }

    public function relationship(string $name): static
    {
        $this->relationshipName = $name;

        return $this;
    }

    /**
     * Phase-2 placeholder — accepted today so Resource configs do not
     * break when inline creation ships.
     */
    public function canAdd(bool $allowed = true): static
    {
        $this->canAdd = $allowed;

        return $this;
    }

    /**
     * Phase-2 placeholder.
     */
    public function canEdit(bool $allowed = true): static
    {
        $this->canEdit = $allowed;

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

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'relatedResource' => $this->relatedResource,
            'relationship' => $this->relationshipName,
            'canAdd' => $this->canAdd,
            'canEdit' => $this->canEdit,
        ];
    }
}
