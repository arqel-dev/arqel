<?php

declare(strict_types=1);

namespace Arqel\FieldsAdvanced\Types;

use Arqel\Fields\Field;
use Arqel\FieldsAdvanced\Blocks\Block;
use InvalidArgumentException;

/**
 * Builder field — a heterogeneous repeater whose items are typed
 * **blocks**. CMS-style page builders, landing-page editors and
 * email composers are the canonical use case: the user picks from
 * a palette of registered block types, each contributing its own
 * inner form schema.
 *
 * **PHP scope of FIELDS-ADV-006 is configuration + payload only.**
 * The React `BuilderInput.tsx` component (block palette, inline
 * inserter, drag-drop reorder, per-block schema rendering) lands
 * in a future FIELDS-JS-XXX ticket and consumes the props returned
 * by `getTypeSpecificProps()` verbatim.
 *
 * @see PLANNING/09-fase-2-essenciais.md §FIELDS-ADV-006
 */
final class BuilderField extends Field
{
    public const MIN_ITEMS_FLOOR = 0;

    public const MAX_ITEMS_FLOOR = 1;

    protected string $type = 'builder';

    protected string $component = 'BuilderInput';

    /** @var array<string, Block> */
    protected array $blocks = [];

    protected ?int $minItems = null;

    protected ?int $maxItems = null;

    protected bool $reorderable = true;

    protected bool $collapsible = true;

    protected bool $cloneable = true;

    protected ?string $itemLabel = null;

    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * Register the palette of block types available inside the
     * builder. Three input shapes are supported, in order of
     * preference:
     *
     *   1. List of class-strings: `[TextBlock::class, ImageBlock::class]`
     *      — each class is instantiated and keyed by `static::type()`.
     *   2. Map of explicit keys to instances or class-strings:
     *      `['text' => new TextBlock(), 'image' => ImageBlock::class]`
     *      — the explicit key is preserved verbatim.
     *   3. Mixed (instance OR class-string per entry) — class-strings
     *      are instantiated, instances are accepted as-is.
     *
     * Non-`Block` entries are silently filtered so a misconfiguration
     * in PHP never reaches the React side as `null`. Two blocks
     * sharing the same `type()` raise `InvalidArgumentException` —
     * silent collisions would let one block shadow another at render
     * time, which is harder to debug than a fail-fast at boot.
     *
     * @param array<int|string, mixed> $blocks
     */
    public function blocks(array $blocks): static
    {
        $resolved = [];

        foreach ($blocks as $key => $entry) {
            $instance = $this->resolveBlockEntry($entry);

            if ($instance === null) {
                continue;
            }

            $type = is_string($key) ? $key : $instance::type();

            if (array_key_exists($type, $resolved)) {
                throw new InvalidArgumentException(sprintf(
                    'Duplicate block type "%s" registered on BuilderField "%s".',
                    $type,
                    $this->name,
                ));
            }

            $resolved[$type] = $instance;
        }

        $this->blocks = $resolved;

        return $this;
    }

    /**
     * Clamp to ≥0 so the React side never receives a negative
     * minimum (which would underflow Add/Remove arithmetic).
     */
    public function minItems(int $min): static
    {
        $this->minItems = max(self::MIN_ITEMS_FLOOR, $min);

        return $this;
    }

    /**
     * Clamp to ≥1 (a builder capped at 0 makes no sense) and
     * preserve the `min ≤ max` invariant — passing a max smaller
     * than the current min raises immediately rather than letting
     * the misconfiguration surface client-side.
     */
    public function maxItems(int $max): static
    {
        $clamped = max(self::MAX_ITEMS_FLOOR, $max);

        if ($this->minItems !== null && $clamped < $this->minItems) {
            throw new InvalidArgumentException('maxItems must be >= minItems');
        }

        $this->maxItems = $clamped;

        return $this;
    }

    public function reorderable(bool $enable = true): static
    {
        $this->reorderable = $enable;

        return $this;
    }

    public function collapsible(bool $enable = true): static
    {
        $this->collapsible = $enable;

        return $this;
    }

    public function cloneable(bool $enable = true): static
    {
        $this->cloneable = $enable;

        return $this;
    }

    /**
     * Template rendered as the collapsed-block header. Tokens
     * `{{fieldname}}` (and the synthetic `{{type}}`) are interpolated
     * client-side from the block instance's data.
     */
    public function itemLabel(string $template): static
    {
        $this->itemLabel = $template;

        return $this;
    }

    /**
     * @return array{
     *     blocks: array<string, array<string, mixed>>,
     *     minItems: ?int,
     *     maxItems: ?int,
     *     reorderable: bool,
     *     collapsible: bool,
     *     cloneable: bool,
     *     itemLabel: ?string
     * }
     */
    public function getTypeSpecificProps(): array
    {
        $blocks = [];

        foreach ($this->blocks as $type => $block) {
            $blocks[$type] = $block->toArray();
        }

        return [
            'blocks' => $blocks,
            'minItems' => $this->minItems,
            'maxItems' => $this->maxItems,
            'reorderable' => $this->reorderable,
            'collapsible' => $this->collapsible,
            'cloneable' => $this->cloneable,
            'itemLabel' => $this->itemLabel,
        ];
    }

    /**
     * Resolve a single `blocks()` entry into a concrete `Block`
     * instance, or `null` when the entry is neither a `Block`
     * instance nor a class-string of a `Block` subclass.
     */
    private function resolveBlockEntry(mixed $entry): ?Block
    {
        if ($entry instanceof Block) {
            return $entry;
        }

        if (is_string($entry) && is_subclass_of($entry, Block::class)) {
            /** @var Block $instance */
            $instance = new $entry;

            return $instance;
        }

        return null;
    }
}
