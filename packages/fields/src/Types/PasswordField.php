<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

/**
 * Password input with an optional reveal toggle.
 *
 * `revealable(true)` lets the React component flip the `type=password`
 * to `type=text` so the user can verify what they typed.
 */
final class PasswordField extends TextField
{
    protected string $type = 'password';

    protected string $component = 'PasswordInput';

    protected bool $revealable = false;

    public function revealable(bool $revealable = true): static
    {
        $this->revealable = $revealable;

        return $this;
    }

    public function isRevealable(): bool
    {
        return $this->revealable;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            ...parent::getTypeSpecificProps(),
            'revealable' => $this->revealable,
        ];
    }
}
