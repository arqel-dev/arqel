<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\ResourcesExtras;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\User;

/**
 * Resource that overrides the auto-derived slug.
 */
final class TeamMemberResource extends Resource
{
    public static string $model = User::class;

    public static ?string $slug = 'team-members';

    public static ?string $label = 'Team Member';

    public static ?string $pluralLabel = 'Team Members';

    public function fields(): array
    {
        return [];
    }
}
