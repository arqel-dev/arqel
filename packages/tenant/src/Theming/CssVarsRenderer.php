<?php

declare(strict_types=1);

namespace Arqel\Tenant\Theming;

/**
 * Renders a `TenantTheme` into a `<style>` block of CSS custom properties.
 *
 * Defensive: any prop containing characters that could break out of the
 * `<style>` context (`<`, `>`, `"`) is treated as null and omitted. The
 * remaining values are still passed through `htmlspecialchars()` for
 * escaping of `&` etc. The model's column-level validation should
 * already reject these patterns at write time — this is a second layer.
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

            if (! is_string($value) || self::isUnsafe($value)) {
                continue;
            }

            $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $declarations[] = sprintf('%s: %s;', $cssVar, $escaped);
        }

        if ($declarations === []) {
            return '';
        }

        return '<style>:root { '.implode(' ', $declarations).' }</style>';
    }

    private static function isUnsafe(string $value): bool
    {
        return str_contains($value, '<')
            || str_contains($value, '>')
            || str_contains($value, '"');
    }
}
