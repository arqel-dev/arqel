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

    /**
     * The effective field list — the form's fields when a layout-aware
     * form is declared, otherwise the flat fields(). Validation and
     * rendering both read this, so a form() needn't re-declare fields().
     *
     * @return array<int, mixed>
     */
    public function effectiveFields(): array;
}
