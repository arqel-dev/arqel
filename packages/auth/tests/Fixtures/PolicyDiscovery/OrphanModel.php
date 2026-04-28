<?php

declare(strict_types=1);

namespace Arqel\Auth\Tests\Fixtures\PolicyDiscovery;

use Illuminate\Database\Eloquent\Model;

/**
 * Lives in a namespace without `\Models\`, so PolicyDiscovery
 * cannot guess a Policy class for it. Used to assert the
 * "missing policy" branch.
 */
final class OrphanModel extends Model
{
    //
}
