<?php

declare(strict_types=1);

namespace Arqel\Tenant\Theming;

/**
 * Renders a `TenantTheme` into a `<style>:root { … }</style>` block of CSS
 * custom properties.
 *
 * Security: the output context is CSS-inside-`<style>`, so HTML-entity
 * escaping is the *wrong* tool — a value such as `red } body{…}` would close
 * the `:root` rule and inject an arbitrary rule even though it contains no
 * `<`, `>` or `"`. Each theme slot is therefore validated against a strict
 * per-type allowlist for its CSS context:
 *
 *  - colors (`primaryColor`, `secondaryColor`): a hex literal, a safe
 *    `rgb()/rgba()/hsl()/hsla()` functional literal, or a CSS named colour.
 *  - `fontFamily`: family names + generic keywords (letters, digits, spaces,
 *    commas, hyphens and single quotes only).
 *  - `logoUrl`, `faviconUrl`: an `http(s)` absolute URL or a root-relative
 *    path, emitted as a properly-escaped `url('…')` value.
 *
 * A value that fails its slot's allowlist is OMITTED (the variable is simply
 * not rendered) — a malicious value disappears rather than degrading into a
 * broken or partially-escaped declaration. The `<`, `>`, `"` drop is kept as
 * defense-in-depth so nothing can break out of the surrounding `<style>`.
 */
final class CssVarsRenderer
{
    /** @var array<string, string> */
    private const VAR_MAP = [
        'primaryColor' => '--color-primary',
        'secondaryColor' => '--color-secondary',
        'fontFamily' => '--font-family',
        'logoUrl' => '--logo-url',
        'faviconUrl' => '--favicon-url',
    ];

    public static function renderInlineStyle(TenantTheme $theme): string
    {
        if ($theme->isEmpty()) {
            return '';
        }

        $declarations = [];
        $payload = $theme->toArray();

        foreach (self::VAR_MAP as $themeKey => $cssVar) {
            $value = $payload[$themeKey] ?? null;

            if (! is_string($value) || self::hasHtmlBreakout($value)) {
                continue;
            }

            $cssValue = match ($themeKey) {
                'primaryColor', 'secondaryColor' => self::sanitizeColor($value),
                'fontFamily' => self::sanitizeFontFamily($value),
                default => self::sanitizeUrl($value),
            };

            if ($cssValue === null) {
                continue;
            }

            $declarations[] = sprintf('%s: %s;', $cssVar, $cssValue);
        }

        if ($declarations === []) {
            return '';
        }

        return '<style>:root { '.implode(' ', $declarations).' }</style>';
    }

    /**
     * Defense-in-depth: reject anything that could break out of the enclosing
     * `<style>` element regardless of the slot-specific allowlists below.
     */
    private static function hasHtmlBreakout(string $value): bool
    {
        return str_contains($value, '<')
            || str_contains($value, '>')
            || str_contains($value, '"');
    }

    /**
     * Accept a hex colour, a safe `rgb()/rgba()/hsl()/hsla()` functional
     * literal, or a CSS named colour. Returns the trimmed value or null.
     */
    private static function sanitizeColor(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // #rgb / #rgba / #rrggbb / #rrggbbaa
        if (preg_match('/^#?[0-9a-fA-F]{3,8}$/', $value) === 1) {
            return $value;
        }

        // rgb()/rgba()/hsl()/hsla() — only digits, dots, commas, percents and
        // spaces are permitted inside the parentheses, so no rule breakout.
        if (preg_match('/^(?:rgb|rgba|hsl|hsla)\(\s*[0-9.,%\s]+\s*\)$/', $value) === 1) {
            return $value;
        }

        // CSS named colour (e.g. `red`, `rebeccapurple`, `transparent`).
        if (preg_match('/^[a-zA-Z]+$/', $value) === 1) {
            return $value;
        }

        return null;
    }

    /**
     * Accept font-family lists: family names and generic keywords made of
     * letters, digits, spaces, commas, hyphens and single quotes. Rejects any
     * CSS-significant character (`{ } ( ) ; @ : / \\ *` etc.).
     */
    private static function sanitizeFontFamily(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match("/^[A-Za-z0-9 ,'\\-]+$/", $value) === 1) {
            return $value;
        }

        return null;
    }

    /**
     * Accept an `http(s)` absolute URL or a root-relative path and emit it as
     * a properly-escaped `url('…')` value. Rejects `javascript:`, `data:` and
     * any value carrying CSS-breakout characters (`' ) \\` whitespace, etc.).
     */
    private static function sanitizeUrl(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // No whitespace, quotes, parens or backslashes — these could break out
        // of the url('…') value or smuggle extra declarations.
        if (preg_match("/[\\s'\"()\\\\]/", $value) === 1) {
            return null;
        }

        // Root-relative path (single leading slash, not protocol-relative).
        $isRelative = str_starts_with($value, '/') && ! str_starts_with($value, '//');

        if (! $isRelative) {
            $scheme = parse_url($value, PHP_URL_SCHEME);

            if (! in_array($scheme, ['http', 'https'], true)) {
                return null;
            }
        }

        return sprintf("url('%s')", $value);
    }
}
