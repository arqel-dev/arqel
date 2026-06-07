<?php

declare(strict_types=1);

namespace Arqel\Audit\Tests\Fixtures;

use Arqel\Audit\Http\Controllers\RecordActivityController;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy fixture que permite `view`. Exercita o caminho positivo de
 * autorização por Policy no
 * {@see RecordActivityController}: quando uma
 * Policy existe e concede acesso, a timeline do record deve responder 200.
 */
final class AllowViewPolicy
{
    public function view(?Model $user, Model $record): bool
    {
        return true;
    }
}
