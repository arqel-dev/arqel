<?php

declare(strict_types=1);

namespace Arqel\Actions\Types;

use Arqel\Actions\Action;

/**
 * Action rendered on the detail page header (single record
 * context). Behaves like a RowAction but lives outside a Table.
 */
final class HeaderAction extends Action
{
    protected string $type = 'header';
}
