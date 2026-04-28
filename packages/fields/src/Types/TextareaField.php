<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

/**
 * Multi-line text input.
 *
 * Inherits the constraint surface (`maxLength`, `minLength`, `pattern`)
 * from `TextField` because the same checks make sense for textareas,
 * and adds `rows`/`cols` for the rendered `<textarea>` element.
 */
final class TextareaField extends TextField
{
    protected string $type = 'textarea';

    protected string $component = 'TextareaInput';

    protected ?int $rows = null;

    protected ?int $cols = null;

    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function cols(int $cols): static
    {
        $this->cols = $cols;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            ...parent::getTypeSpecificProps(),
            'rows' => $this->rows,
            'cols' => $this->cols,
        ], fn ($value) => $value !== null);
    }
}
