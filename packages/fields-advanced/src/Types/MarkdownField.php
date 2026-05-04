<?php

declare(strict_types=1);

namespace Arqel\FieldsAdvanced\Types;

use Arqel\Fields\Field;

/**
 * Markdown editor + preview field powered by CodeMirror on the React side
 * (`@arqel-dev/fields-advanced/MarkdownInput.tsx`, shipped in a future ticket).
 *
 * The PHP side is configuration-only: it advertises whether the live
 * preview is enabled, the layout mode (`side-by-side`, `tab` or
 * `popup`), the toolbar visibility, the editor row count, the
 * fullscreen toggle and whether scroll is synchronised between editor
 * and preview. The React component reads those props verbatim and
 * wires CodeMirror + remark/rehype-sanitize accordingly.
 *
 * **Server-side sanitisation is the consumer's responsibility.** This
 * field intentionally does NOT run any Markdown→HTML pipeline server-
 * side. The React preview uses `remark` + `rehype-sanitize`, which is
 * the safer default since untrusted Markdown is rendered client-side
 * inside the editor's own preview pane only. If you intend to render
 * the persisted Markdown elsewhere (server-side blade, RSS feed,
 * notification email), you MUST sanitise at write time (FormRequest
 * rules, Eloquent mutator) or render through a hardened pipeline.
 * Treat any value submitted from the editor as untrusted Markdown.
 */
final class MarkdownField extends Field
{
    public const PREVIEW_MODE_SIDE_BY_SIDE = 'side-by-side';

    public const PREVIEW_MODE_TAB = 'tab';

    public const PREVIEW_MODE_POPUP = 'popup';

    /** @var list<string> */
    private const PREVIEW_MODES = [
        self::PREVIEW_MODE_SIDE_BY_SIDE,
        self::PREVIEW_MODE_TAB,
        self::PREVIEW_MODE_POPUP,
    ];

    public const DEFAULT_ROWS = 10;

    public const MIN_ROWS = 3;

    protected string $type = 'markdown';

    protected string $component = 'MarkdownInput';

    protected bool $preview = true;

    protected string $previewMode = self::PREVIEW_MODE_SIDE_BY_SIDE;

    protected bool $toolbar = true;

    protected int $rows = self::DEFAULT_ROWS;

    protected bool $fullscreen = true;

    protected bool $syncScroll = true;

    public function preview(bool $enable = true): static
    {
        $this->preview = $enable;

        return $this;
    }

    /**
     * Set the preview layout mode. Unknown values silently fall back to
     * `side-by-side` so misconfigurations never break the React side.
     */
    public function previewMode(string $mode): static
    {
        $this->previewMode = in_array($mode, self::PREVIEW_MODES, true)
            ? $mode
            : self::PREVIEW_MODE_SIDE_BY_SIDE;

        return $this;
    }

    public function toolbar(bool $enable = true): static
    {
        $this->toolbar = $enable;

        return $this;
    }

    /**
     * Clamp to ≥3 so the editor never renders a degenerate textarea
     * height that would hide content on first render.
     */
    public function rows(int $rows): static
    {
        $this->rows = max(self::MIN_ROWS, $rows);

        return $this;
    }

    public function fullscreen(bool $enable = true): static
    {
        $this->fullscreen = $enable;

        return $this;
    }

    public function syncScroll(bool $enable = true): static
    {
        $this->syncScroll = $enable;

        return $this;
    }

    /**
     * @return array{
     *     preview: bool,
     *     previewMode: string,
     *     toolbar: bool,
     *     rows: int,
     *     fullscreen: bool,
     *     syncScroll: bool
     * }
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'preview' => $this->preview,
            'previewMode' => $this->previewMode,
            'toolbar' => $this->toolbar,
            'rows' => $this->rows,
            'fullscreen' => $this->fullscreen,
            'syncScroll' => $this->syncScroll,
        ];
    }
}
