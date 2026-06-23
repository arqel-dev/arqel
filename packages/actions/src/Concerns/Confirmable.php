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

    /**
     * Custom submit-button label. When null the localized default
     * (`arqel::actions.confirm.submit`) is resolved lazily at
     * serialization so the active request locale applies.
     */
    protected ?string $modalSubmitButtonLabel = null;

    /**
     * Custom cancel-button label. When null the localized default
     * (`arqel::actions.confirm.cancel`) is resolved lazily at
     * serialization so the active request locale applies.
     */
    protected ?string $modalCancelButtonLabel = null;

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
            'heading' => self::localizeConfirmable($this->modalHeading),
            'description' => self::localizeConfirmable($this->modalDescription),
            'icon' => $this->modalIcon,
            'color' => $this->modalColor,
            'requiresText' => self::localizeConfirmable($this->modalConfirmationRequiresText),
            'submitLabel' => $this->modalSubmitButtonLabel !== null
                ? self::localizeConfirmable($this->modalSubmitButtonLabel)
                : (string) __('arqel::actions.confirm.submit'),
            'cancelLabel' => $this->modalCancelButtonLabel !== null
                ? self::localizeConfirmable($this->modalCancelButtonLabel)
                : (string) __('arqel::actions.confirm.cancel'),
        ], fn ($v) => $v !== null);
    }

    /**
     * Resolve a stored string against the translator when it is a
     * registered key, otherwise return it verbatim. Resolved lazily at
     * serialization so the active request locale applies (#i18n).
     */
    private static function localizeConfirmable(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $translated = __($value);

        return is_string($translated) ? $translated : $value;
    }
}
