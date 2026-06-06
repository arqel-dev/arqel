<?php

declare(strict_types=1);

namespace Arqel\Versioning\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Policy fixture que permite `view`. Cobre o caminho positivo (200)
 * quando uma Policy está registrada mas concede acesso.
 */
final class AllowViewPolicy
{
    public function view(?Model $user, Model $record): bool
    {
        return true;
    }
}
