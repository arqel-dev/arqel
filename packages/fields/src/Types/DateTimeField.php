<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

/**
 * Date + time input.
 *
 * Defaults to `Y-m-d H:i:s` storage with `d/m/Y H:i` display.
 * `seconds(true)` flips display to `d/m/Y H:i:s`.
 */
final class DateTimeField extends DateField
{
    protected string $type = 'dateTime';

    protected string $component = 'DateTimeInput';

    protected string $format = 'Y-m-d H:i:s';

    protected string $displayFormat = 'd/m/Y H:i';

    protected bool $seconds = false;

    public function seconds(bool $show = true): static
    {
        $this->seconds = $show;
        $this->displayFormat = $show ? 'd/m/Y H:i:s' : 'd/m/Y H:i';

        return $this;
    }

    public function showsSeconds(): bool
    {
        return $this->seconds;
    }

    /**
     * @return array<int, string>
     */
    public function getDefaultRules(): array
    {
        return ['date'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            ...parent::getTypeSpecificProps(),
            'seconds' => $this->seconds,
        ];
    }
}
