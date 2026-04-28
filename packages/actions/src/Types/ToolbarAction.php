<?php

declare(strict_types=1);

namespace Arqel\Actions\Types;

use Arqel\Actions\Action;

/**
 * Standalone toolbar action rendered above a Table. No record
 * context — typical examples: "Create", "Export CSV".
 */
final class ToolbarAction extends Action
{
    protected string $type = 'toolbar';
}
