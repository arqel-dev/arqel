<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

use Arqel\Fields\Field;
use Closure;

/**
 * Date input.
 *
 * Subclassed by `DateTimeField` for the date+time visual.
 *
 * `format` is the storage shape Laravel persists (`Y-m-d`);
 * `displayFormat` is what the React component shows the user
 * (PT-BR convention: `d/m/Y`). Server-side timezone is always
 * UTC; `timezone()` lets the field declare the user's timezone
 * for display conversion.
 *
 * `minDate()` and `maxDate()` accept either a literal string or
 * a Closure that resolves at serialise time so values like
 * "now" or "first day of next month" can react to the request.
 */
class DateField extends Field
{
    protected string $type = 'date';

    protected string $component = 'DateInput';

    protected string|Closure|null $minDate = null;

    protected string|Closure|null $maxDate = null;

    protected string $format = 'Y-m-d';

    protected string $displayFormat = 'd/m/Y';

    protected bool $closeOnSelect = true;

    protected ?string $timezone = null;

    public function minDate(string|Closure $date): static
    {
        $this->minDate = $date;

        return $this;
    }

    public function maxDate(string|Closure $date): static
    {
        $this->maxDate = $date;

        return $this;
    }

    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function displayFormat(string $format): static
    {
        $this->displayFormat = $format;

        return $this;
    }

    public function closeOnDateSelection(bool $close = true): static
    {
        $this->closeOnSelect = $close;

        return $this;
    }

    public function timezone(string $tz): static
    {
        $this->timezone = $tz;

        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getDisplayFormat(): string
    {
        return $this->displayFormat;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * @return array<int, string>
     */
    public function getDefaultRules(): array
    {
        return ['date'];
    }

    /**
     * Resolve a min/max bound. Closures are invoked once with no
     * arguments; non-string returns are coerced to `null` so the
     * payload stays predictable.
     */
    protected function resolveBound(string|Closure|null $value): ?string
    {
        if ($value instanceof Closure) {
            $resolved = $value();

            return is_string($resolved) ? $resolved : null;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            'minDate' => $this->resolveBound($this->minDate),
            'maxDate' => $this->resolveBound($this->maxDate),
            'format' => $this->format,
            'displayFormat' => $this->displayFormat,
            'closeOnSelect' => $this->closeOnSelect,
            'timezone' => $this->timezone,
        ], fn ($value) => $value !== null);
    }
}
