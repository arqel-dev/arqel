<?php

declare(strict_types=1);

namespace Arqel\Tenant\Tests\Fixtures;

use Arqel\Tenant\Concerns\HasFeatures;
use Illuminate\Database\Eloquent\Model;

/**
 * Test-only Eloquent fixture for the {@see HasFeatures} trait.
 * Casts `features` as an array so JSON-column round-trips behave
 * the same way real tenant models do.
 */
class FeatureGatedTenant extends Model
{
    use HasFeatures;

    protected $table = 'feature_gated_tenants';

    protected $guarded = [];

    public $timestamps = false;

    /** @var array<string, string> */
    protected $casts = [
        'features' => 'array',
    ];
}
