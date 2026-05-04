<?php

declare(strict_types=1);

namespace Arqel\Core\Contracts;

/**
 * Implemented by classes that declare a Field schema.
 *
 * The shape of each entry in the returned array is defined by the
 * `arqel-dev/fields` package (FIELDS-* tickets). This contract is kept
 * intentionally loose at the type level — we cannot type-hint a
 * `Field` class until that package exists.
 */
interface HasFields
{
    /**
     * @return array<int, mixed>
     */
    public function fields(): array;
}
