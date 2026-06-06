<?php

declare(strict_types=1);

namespace Arqel\Versioning\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Policy fixture que nega `view`. Registrada via
 * `Gate::policy(Article::class, DenyViewPolicy::class)` para exercitar
 * o caminho de autorização por Policy no `VersionHistoryController`,
 * garantindo que o snapshot (payload) não vaza para usuários sem acesso.
 */
final class DenyViewPolicy
{
    public function view(?Model $user, Model $record): bool
    {
        return false;
    }
}
