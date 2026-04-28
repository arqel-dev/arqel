<?php

declare(strict_types=1);

namespace Arqel\Table\Columns;

use Arqel\Table\Column;

/**
 * Date / datetime cell. `since()` switches to relative ("2 hours
 * ago") rendering on the React side.
 */
final class DateColumn extends Column
{
    public const string MODE_DATE = 'date';

    public const string MODE_DATETIME = 'datetime';

    public const string MODE_SINCE = 'since';

    protected string $type = 'date';

    protected string $mode = self::MODE_DATE;

    protected string $format = 'Y-m-d';

    public function date(string $format = 'Y-m-d'): static
    {
        $this->mode = self::MODE_DATE;
        $this->format = $format;

        return $this;
    }

    public function dateTime(string $format = 'Y-m-d H:i:s'): static
    {
        $this->mode = self::MODE_DATETIME;
        $this->format = $format;

        return $this;
    }

    public function since(): static
    {
        $this->mode = self::MODE_SINCE;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'mode' => $this->mode,
            'format' => $this->format,
        ];
    }
}
