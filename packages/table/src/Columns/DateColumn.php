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

    protected ?string $timezone = null;

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
     * Declare the display timezone for this column. The React `DateCell`
     * passes this to `Intl.DateTimeFormat({ timeZone })` so a UTC-stored
     * value renders in the intended zone instead of the viewer's browser
     * zone. Mirrors `DateField::timezone()` in arqel/fields.
     */
    public function timezone(string $tz): static
    {
        $this->timezone = $tz;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            'mode' => $this->mode,
            'format' => $this->format,
            'timezone' => $this->timezone,
        ], fn ($value) => $value !== null);
    }
}
