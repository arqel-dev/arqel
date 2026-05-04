<?php

declare(strict_types=1);

namespace Arqel\FieldsAdvanced\Types;

use Arqel\Fields\Field;
use Closure;
use InvalidArgumentException;

/**
 * Tag input field powered by `TagsInput.tsx` on the React side
 * (shipped in a future FIELDS-JS-XXX ticket). Useful for free-form
 * categorisation, label management, hashtag-style metadata or any
 * many-to-many relationship that is rendered as chips with an
 * autocomplete dropdown.
 *
 * The PHP side is configuration-only: it advertises the suggestion
 * list (eager array or lazy `Closure`), the toggles for tag creation,
 * uniqueness and maximum count, and the paste separator. The React
 * component is responsible for the actual chip rendering, keyboard
 * handling (Enter to add, Backspace to remove last) and persistence
 * shape — pair this with an Eloquent `array` cast (or a JSON column)
 * on the underlying attribute, or with a many-to-many sync block in
 * the consumer's controller.
 *
 * Eloquent `fromRelationship(...)` integration (Spatie/laravel-tags
 * or arbitrary HasMany/BelongsToMany sync) is deferred to a follow-up
 * ticket because it requires Field hydration hooks that don't exist
 * yet in `arqel-dev/core`. Current scope is config-only.
 *
 * @see PLANNING/09-fase-2-essenciais.md §FIELDS-ADV-008
 */
final class TagsField extends Field
{
    protected string $type = 'tags';

    protected string $component = 'TagsInput';

    /**
     * @var array<int, string>|Closure
     */
    protected array|Closure $suggestions = [];

    protected bool $creatable = true;

    protected ?int $maxTags = null;

    protected string $separator = ',';

    protected bool $unique = true;

    /**
     * Convenience constructor mirroring `KeyValueField::make`.
     * Equivalent to `new TagsField($name)` but reads naturally
     * as `TagsField::make('categories')->suggestions(...)`.
     */
    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * Configure the autocomplete dropdown. Accepts either an eager
     * array of strings (filtered silently — non-string entries are
     * dropped before they reach React) or a `Closure` that returns
     * an array, resolved lazily inside `getTypeSpecificProps()` so
     * suggestions can depend on runtime context (auth user, tenant
     * scope, cached query). Closures returning a non-array value
     * collapse to `[]` so a misconfigured callback never breaks the
     * React render.
     *
     * @param array<int, mixed>|Closure $tags
     */
    public function suggestions(array|Closure $tags): static
    {
        if (is_array($tags)) {
            $this->suggestions = array_values(array_filter(
                $tags,
                static fn (mixed $tag): bool => is_string($tag),
            ));

            return $this;
        }

        $this->suggestions = $tags;

        return $this;
    }

    /**
     * Toggle ad-hoc tag creation. When disabled, the React side will
     * only let the user pick from the suggestion list — Enter on an
     * unknown value is a no-op.
     */
    public function creatable(bool $enable = true): static
    {
        $this->creatable = $enable;

        return $this;
    }

    /**
     * Cap the total number of tags the user can attach. Values below
     * 1 are clamped to 1 (a `TagsField` with capacity zero is the
     * same as not rendering the field). Pass `null` to reset to the
     * "no cap" default.
     */
    public function maxTags(?int $max): static
    {
        if ($max === null) {
            $this->maxTags = null;

            return $this;
        }

        $this->maxTags = max(1, $max);

        return $this;
    }

    /**
     * Configure the paste separator. When the user pastes a string
     * containing this token, the React side splits it into multiple
     * tags. Empty strings are rejected with `InvalidArgumentException`
     * because they would either freeze the splitter or produce an
     * infinite stream of empty tags.
     */
    public function separator(string $sep): static
    {
        if ($sep === '') {
            throw new InvalidArgumentException('separator must not be empty');
        }

        $this->separator = $sep;

        return $this;
    }

    /**
     * Toggle UI-side deduplication. When `true` (default) the React
     * component drops duplicate entries before they reach the form
     * state; when `false` duplicates are preserved (useful for tag
     * histograms or weighted vocabularies).
     *
     * Renamed from `unique()` (per the FIELDS-ADV-008 spec) to
     * `uniqueTags()` to avoid colliding with the validation helper
     * `Field::unique(?string $table, ?string $column, mixed $ignorable)`
     * inherited from `HasValidation`. The wire-format key is still
     * `unique` so the React contract documented in the spec stays
     * intact.
     */
    public function uniqueTags(bool $enable = true): static
    {
        $this->unique = $enable;

        return $this;
    }

    /**
     * @return array{
     *     suggestions: array<int, string>,
     *     creatable: bool,
     *     maxTags: int|null,
     *     separator: string,
     *     unique: bool
     * }
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'suggestions' => $this->resolveSuggestions(),
            'creatable' => $this->creatable,
            'maxTags' => $this->maxTags,
            'separator' => $this->separator,
            'unique' => $this->unique,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolveSuggestions(): array
    {
        if ($this->suggestions instanceof Closure) {
            $resolved = ($this->suggestions)();

            if (! is_array($resolved)) {
                return [];
            }

            return array_values(array_filter(
                $resolved,
                static fn (mixed $tag): bool => is_string($tag),
            ));
        }

        return $this->suggestions;
    }
}
