<?php

declare(strict_types=1);

namespace Arqel\Fields;

use Arqel\Fields\Types\BelongsToField;
use Arqel\Fields\Types\HasManyField;

/**
 * Inspects a Resource's field list and returns the Eloquent
 * relations the controller should eager-load to avoid N+1 queries
 * (RF-P-03, ADR-003 §Consequences).
 *
 * Today we recognise `BelongsToField` and `HasManyField` — both
 * carry a `relationshipName`. Custom field types that wrap a
 * relation can opt in by implementing the `EagerLoadable` contract
 * (TODO: add when a third type needs it).
 *
 * The output is a deduplicated list of relation names suitable for
 * `Builder::with(...)`. Subsequent integration with the resource
 * controller (CORE-006) wires it into `index()` automatically; for
 * now apps can call it manually inside `indexQuery()`.
 */
final class EagerLoadingResolver
{
    /**
     * @param array<int, mixed> $fields
     *
     * @return array<int, string>
     */
    public static function resolve(array $fields): array
    {
        $relations = [];

        foreach ($fields as $field) {
            if ($field instanceof BelongsToField || $field instanceof HasManyField) {
                $name = $field->getRelationshipName();
                if ($name !== '' && ! in_array($name, $relations, true)) {
                    $relations[] = $name;
                }
            }
        }

        return $relations;
    }
}
