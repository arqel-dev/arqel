<?php

declare(strict_types=1);

namespace Arqel\Actions\Concerns;

/**
 * Confirmation modal config for an Action. Serialised into the
 * payload so the React side can render the modal; server-side
 * authorization still owns the strict gate.
 */
trait Confirmable
{
    public const string MODAL_COLOR_DESTRUCTIVE = 'destructive';

    public const string MODAL_COLOR_WARNING = 'warning';

    public const string MODAL_COLOR_INFO = 'info';

    protected bool $requiresConfirmation = false;

    protected ?string $modalHeading = null;

    protected ?string $modalDescription = null;

    protected ?string $modalIcon = null;

    protected string $modalColor = self::MODAL_COLOR_DESTRUCTIVE;

    protected ?string $modalConfirmationRequiresText = null;

    protected string $modalSubmitButtonLabel = 'Confirm';

    protected string $modalCancelButtonLabel = 'Cancel';

    public function requiresConfirmation(bool $required = true): static
    {
        $this->requiresConfirmation = $required;

        return $this;
    }

    public function modalHeading(string $heading): static
    {
        $this->modalHeading = $heading;
        $this->requiresConfirmation = true;

        return $this;
    }

    public function modalDescription(string $description): static
    {
        $this->modalDescription = $description;
        $this->requiresConfirmation = true;

        return $this;
    }

    public function modalIcon(string $icon): static
    {
        $this->modalIcon = $icon;

        return $this;
    }

    public function modalColor(string $color): static
    {
        $this->modalColor = in_array($color, [
            self::MODAL_COLOR_DESTRUCTIVE,
            self::MODAL_COLOR_WARNING,
            self::MODAL_COLOR_INFO,
        ], true)
            ? $color
            : self::MODAL_COLOR_DESTRUCTIVE;

        return $this;
    }

    public function modalConfirmationRequiresText(string $text): static
    {
        $this->modalConfirmationRequiresText = $text;
        $this->requiresConfirmation = true;

        return $this;
    }

    public function modalSubmitButtonLabel(string $label): static
    {
        $this->modalSubmitButtonLabel = $label;

        return $this;
    }

    public function modalCancelButtonLabel(string $label): static
    {
        $this->modalCancelButtonLabel = $label;

        return $this;
    }

    public function isRequiringConfirmation(): bool
    {
        return $this->requiresConfirmation;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConfirmationConfig(): ?array
    {
        if (! $this->requiresConfirmation) {
            return null;
        }

        return array_filter([
            'heading' => $this->modalHeading,
            'description' => $this->modalDescription,
            'icon' => $this->modalIcon,
            'color' => $this->modalColor,
            'requiresText' => $this->modalConfirmationRequiresText,
            'submitLabel' => $this->modalSubmitButtonLabel,
            'cancelLabel' => $this->modalCancelButtonLabel,
        ], fn ($v) => $v !== null);
    }
}
