<?php

declare(strict_types=1);

namespace Arqel\Versioning\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Policy fixture que nega `update` para qualquer record. Registrada via
 * `Gate::policy(Article::class, DenyUpdatePolicy::class)` para exercitar
 * o caminho de autorização por Policy (não por named-gate) no
 * `VersionRestoreController`.
 */
final class DenyUpdatePolicy
{
    public function update(?Model $user, Model $record): bool
    {
        return false;
    }
}
