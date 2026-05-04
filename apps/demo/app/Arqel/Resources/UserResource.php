<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\User;

final class UserResource
{
    public static string $model = User::class;

    public static string $slug = 'users';

    public static string $label = 'User';

    public static string $pluralLabel = 'Users';

    /** @return array<int, array<string, mixed>> */
    public static function fields(): array
    {
        return [
            ['name' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'type' => 'email', 'required' => true],
        ];
    }
}
