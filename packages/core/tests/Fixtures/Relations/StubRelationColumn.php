<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Relations;

use Illuminate\Database\Eloquent\Model;

/**
 * Duck-typed stand-in for `Arqel\Table\Column` — mirrors the shape
 * `InertiaDataBuilder::resolveRedactedColumnNames()` reads via
 * `method_exists` (`getName()` + `isVisibleFor(?Model): bool`), without a
 * hard dep on arqel-dev/table (core stays dependency-free). Mirrors the
 * existing `StubField`/`StubRelationTable` pattern in this same directory.
 *
 * `$visible` is a fixed boolean rather than a closure so tests can
 * construct a column that is always redacted (`canSee(fn () => false)`
 * equivalent) or always visible (a plain column, no `canSee`), matching
 * the two scenarios `RelationIndexTest` needs.
 */
final class StubRelationColumn
{
    public function __construct(
        private readonly string $name,
        private readonly bool $visible = true,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isVisibleFor(?Model $record): bool
    {
        return $this->visible;
    }

    /**
     * Mirrors the shape of `Arqel\Table\Column::toArray()` (name/label at
     * minimum) so tests exercising `InertiaDataBuilder::serializeTableSchema()`
     * can assert the React `<DataTable>`'s required `col.name` survives
     * serialization — the very thing a raw `$table->toArray()` (unserialized
     * Column objects) fails to produce.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => ucfirst($this->name),
            'type' => 'text',
            'sortable' => false,
            'searchable' => false,
        ];
    }
}
