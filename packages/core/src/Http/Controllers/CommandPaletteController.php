<?php

declare(strict_types=1);

namespace Arqel\Core\Http\Controllers;

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\CommandRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /admin/commands?q=...` — JSON endpoint backing the React
 * `<CommandPalette>` (component lands in a later ticket).
 *
 * Always returns `{ commands: [...] }`, even when the query is
 * empty. The registry handles fuzzy filtering and the 20-item cap.
 */
final class CommandPaletteController
{
    public function __invoke(Request $request, CommandRegistry $registry): JsonResponse
    {
        $rawQuery = $request->input('q', '');
        $query = is_string($rawQuery) ? $rawQuery : '';

        $user = $request->user();
        if ($user !== null && ! $user instanceof Authenticatable) {
            $user = null;
        }

        $commands = $registry->resolveFor($user, $query);

        return response()->json([
            'commands' => array_map(
                static fn (Command $command): array => $command->toArray(),
                $commands,
            ),
        ]);
    }
}
