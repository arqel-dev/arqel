<?php

declare(strict_types=1);

namespace Arqel\Actions\Types;

use Arqel\Actions\Action;

/**
 * Per-row action rendered inside a Table row. Callback (or URL)
 * receives the single Eloquent record for that row.
 */
final class RowAction extends Action
{
    protected string $type = 'row';
}
