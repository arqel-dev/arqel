<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\User;

final class UserResource extends Resource
{
    public static string $model = User::class;

    public static ?string $navigationIcon = 'heroicon-o-user';

    public static ?string $navigationGroup = 'System';

    public static ?int $navigationSort = 10;

    public function fields(): array
    {
        return [];
    }
}
