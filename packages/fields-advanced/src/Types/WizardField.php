<?php

declare(strict_types=1);

namespace Arqel\FieldsAdvanced\Types;

use Arqel\Fields\Field;
use Arqel\FieldsAdvanced\Steps\Step;
use InvalidArgumentException;

/**
 * Wizard field — multi-step form layout. Each step is a `Step` value
 * object holding its own slice of the form schema. The React side
 * renders one step at a time and uses `<Activity>` (React 19.2) to
 * preserve previous-step state without remounting subtrees.
 *
 * **PHP scope of FIELDS-ADV-009 is configuration + payload only.**
 * The React `WizardInput.tsx` renderer (progress indicator, per-step
 * validation, `<Activity>`-based state preservation) lands in a
 * future FIELDS-JS-XXX ticket. Form/FormRenderer integration (treating
 * Wizard as a layout component instead of a leaf field) is deferred
 * to a cross-package follow-up — at this scaffold layer the wizard
 * presents itself as a regular `Field` type.
 *
 * @see PLANNING/09-fase-2-essenciais.md §FIELDS-ADV-009
 */
final class WizardField extends Field
{
    protected string $type = 'wizard';

    protected string $component = 'WizardInput';

    /** @var array<int, Step> */
    protected array $steps = [];

    protected bool $persistInUrl = false;

    protected bool $skippable = false;

    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * Register the ordered list of steps shown by the wizard.
     * Non-`Step` entries are silently filtered so a misconfiguration
     * in PHP never reaches the React side as `null`. Two steps sharing
     * the same `name` raise `InvalidArgumentException` — silent
     * collisions would let `persistInUrl` query params point at the
     * wrong step (last-wins) and break per-step validation routing.
     *
     * @param array<int, mixed> $steps
     */
    public function steps(array $steps): static
    {
        /** @var array<int, Step> $resolved */
        $resolved = array_values(array_filter(
            $steps,
            static fn (mixed $step): bool => $step instanceof Step,
        ));

        $seen = [];
        foreach ($resolved as $step) {
            $name = $step->getName();
            if (array_key_exists($name, $seen)) {
                throw new InvalidArgumentException(
                    "Step names must be unique; duplicate: {$name}",
                );
            }
            $seen[$name] = true;
        }

        $this->steps = $resolved;

        return $this;
    }

    /**
     * When `true`, the React side syncs the current step index to a
     * query param so deep-linking and reload preserve position.
     */
    public function persistInUrl(bool $enable = true): static
    {
        $this->persistInUrl = $enable;

        return $this;
    }

    /**
     * When `true`, allow non-linear navigation: the user may jump to
     * arbitrary steps via the progress indicator without completing
     * the previous one. Default is the safer linear flow.
     */
    public function skippable(bool $enable = true): static
    {
        $this->skippable = $enable;

        return $this;
    }

    /**
     * @return array{
     *     steps: array<int, array<string, mixed>>,
     *     persistInUrl: bool,
     *     skippable: bool
     * }
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'steps' => array_map(
                static fn (Step $step): array => $step->toArray(),
                $this->steps,
            ),
            'persistInUrl' => $this->persistInUrl,
            'skippable' => $this->skippable,
        ];
    }
}
