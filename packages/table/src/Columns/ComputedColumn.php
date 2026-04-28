<?php

declare(strict_types=1);

namespace Arqel\Table\Columns;

use Arqel\Table\Column;

/**
 * Computed cell — the value is produced by a Closure rather than
 * read from the model. Sortable by default is `false` because
 * sorting on a computed value requires a DB-level expression that
 * the column does not own. Apps that know how to express the
 * computation in SQL can call `sortable()` explicitly.
 */
final class ComputedColumn extends Column
{
    protected string $type = 'computed';
}
