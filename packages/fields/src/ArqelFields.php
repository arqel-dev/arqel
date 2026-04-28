<?php

declare(strict_types=1);

namespace Arqel\Fields;

/**
 * Inventory helper for the Field catalogue.
 *
 * `skill()` returns a structured snapshot of every type registered
 * in the `FieldFactory` and every macro currently in memory. The
 * MCP server (Phase 2) consumes this to advertise available field
 * types to AI agents; humans can call it for debugging or to drop
 * into a doc page.
 *
 * Output is JSON-serialisable: macros are reported by name only
 * (Closures are not included), types by FQCN. Apps can pretty-print
 * via `json_encode(ArqelFields::skill(), JSON_PRETTY_PRINT)`.
 */
final class ArqelFields
{
    /**
     * @return array{types: array<string, class-string<Field>>, macros: array<int, string>}
     */
    public static function skill(): array
    {
        return [
            'types' => self::types(),
            'macros' => self::macros(),
        ];
    }

    /**
     * @return array<string, class-string<Field>>
     */
    public static function types(): array
    {
        return FieldFactory::getRegisteredTypes();
    }

    /**
     * @return array<int, string>
     */
    public static function macros(): array
    {
        return FieldFactory::getRegisteredMacros();
    }
}
