<?php

declare(strict_types=1);

namespace Arqel\Core\Panel;

use RuntimeException;

final class PanelNotFoundException extends RuntimeException
{
    public function __construct(string $panelId)
    {
        parent::__construct("No panel registered with id [{$panelId}].");
    }
}
