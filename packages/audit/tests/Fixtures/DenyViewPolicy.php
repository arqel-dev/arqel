<?php

declare(strict_types=1);

namespace Arqel\Audit\Tests\Fixtures;

use Arqel\Audit\Http\Controllers\RecordActivityController;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy fixture que nega `view`. Registrada via
 * `Gate::policy(FakeAuditableModel::class, DenyViewPolicy::class)` para
 * exercitar o caminho de negação por Policy no
 * {@see RecordActivityController}, garantindo
 * que a timeline de um record protegido não vaza para usuários sem acesso.
 */
final class DenyViewPolicy
{
    public function view(?Model $user, Model $record): bool
    {
        return false;
    }
}
