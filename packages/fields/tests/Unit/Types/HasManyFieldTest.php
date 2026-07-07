<?php

declare(strict_types=1);

use Arqel\Fields\Tests\Fixtures\StubResource;
use Arqel\Fields\Types\HasManyField;

it('serialises a component name matching the registered React component key', function (): void {
    $field = HasManyField::make('posts', StubResource::class);

    // The React side registers this field's renderer under the key
    // 'HasManyReadonly' (see packages-js/fields-js/src/register.ts). The
    // PHP-declared component string must match exactly, or the field
    // will fail to resolve to a renderer on the frontend.
    expect($field->getComponent())->toBe('HasManyReadonly');
});
