<?php

declare(strict_types=1);

namespace Arqel\Widgets\Tests\Fixtures;

use Arqel\Widgets\Widget;

/**
 * Segundo widget de teste — existe para provar que o merge do bridge
 * acrescenta sem sobrescrever o que a aplicação já registrou.
 */
final class SecondaryWidget extends Widget
{
    protected string $type = 'secondary';

    protected string $component = 'SecondaryWidget';

    public function data(): array
    {
        return ['ok' => true];
    }
}
