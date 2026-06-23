<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\User;

/**
 * Resource whose explicit static metadata are translation keys, used to
 * prove labels/plural-labels/navigation-group localize at serialization
 * time via the active request locale.
 */
final class TranslatableResource extends Resource
{
    public static string $model = User::class;

    public static ?string $label = 'app::resources.member';

    public static ?string $pluralLabel = 'app::resources.members';

    public static ?string $navigationGroup = 'app::resources.group';

    public function fields(): array
    {
        return [];
    }
}
