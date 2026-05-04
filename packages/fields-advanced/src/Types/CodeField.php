<?php

declare(strict_types=1);

namespace Arqel\FieldsAdvanced\Types;

use Arqel\Fields\Field;

/**
 * Code editor field powered by CodeMirror 6 + Shiki on the React side
 * (`@arqel-dev/fields-advanced/CodeInput.tsx`, shipped in a future
 * FIELDS-JS ticket).
 *
 * The PHP side is configuration-only: it advertises the language
 * grammar to load, an optional theme override (otherwise the React
 * side inherits the panel's dark/light toggle), whether line numbers
 * and word wrap are visible, the tab size in spaces, the readonly
 * flag and an optional minimum editor height. The React component
 * reads those props verbatim and lazy-loads the requested Shiki
 * grammar so unused languages never enter the bundle.
 *
 * **The PHP side does not validate or sanitise the submitted source
 * code.** Persisting user-authored code carries its own threat model
 * (XSS if rendered raw, command injection if eval'd) and is the
 * consumer's responsibility — sanitise at write time (FormRequest
 * rules, Eloquent mutators) or render through a hardened pipeline
 * before display.
 */
final class CodeField extends Field
{
    public const DEFAULT_LANGUAGE = 'plaintext';

    public const DEFAULT_TAB_SIZE = 2;

    public const MIN_TAB_SIZE = 1;

    public const MIN_HEIGHT_FLOOR = 0;

    protected string $type = 'code';

    protected string $component = 'CodeInput';

    protected string $language = self::DEFAULT_LANGUAGE;

    protected ?string $theme = null;

    protected bool $lineNumbers = true;

    protected bool $wordWrap = false;

    protected int $tabSize = self::DEFAULT_TAB_SIZE;

    protected ?int $minHeight = null;

    public function language(string $lang): static
    {
        $this->language = $lang;

        return $this;
    }

    /**
     * Set (or unset, with `null`) the Shiki theme. The React side
     * falls back to the panel's dark/light toggle when this is
     * `null`, which is the recommended default.
     */
    public function theme(?string $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function lineNumbers(bool $show = true): static
    {
        $this->lineNumbers = $show;

        return $this;
    }

    public function wordWrap(bool $wrap = true): static
    {
        $this->wordWrap = $wrap;

        return $this;
    }

    /**
     * Clamp to ≥1 so the editor never receives a non-positive tab
     * size, which CodeMirror would reject at construction.
     */
    public function tabSize(int $size): static
    {
        $this->tabSize = max(self::MIN_TAB_SIZE, $size);

        return $this;
    }

    /**
     * Set the minimum editor height in CSS pixels. Pass `null` to
     * reset to the React-side default. Negative values are clamped
     * to 0 so misconfigurations never collapse the editor.
     */
    public function minHeight(?int $px): static
    {
        $this->minHeight = $px === null
            ? null
            : max(self::MIN_HEIGHT_FLOOR, $px);

        return $this;
    }

    /**
     * @return array{
     *     language: string,
     *     theme: ?string,
     *     lineNumbers: bool,
     *     wordWrap: bool,
     *     tabSize: int,
     *     readonly: bool,
     *     minHeight: ?int
     * }
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'language' => $this->language,
            'theme' => $this->theme,
            'lineNumbers' => $this->lineNumbers,
            'wordWrap' => $this->wordWrap,
            'tabSize' => $this->tabSize,
            'readonly' => $this->readonly,
            'minHeight' => $this->minHeight,
        ];
    }
}
