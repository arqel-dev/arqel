<?php

declare(strict_types=1);

namespace Arqel\FieldsAdvanced;

use Arqel\Fields\FieldFactory;
use Arqel\FieldsAdvanced\Types\RichTextField;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel/fields-advanced`.
 *
 * Registers advanced field types into the shared `FieldFactory` so apps
 * can call `FieldFactory::richText('content')` (and, in subsequent
 * tickets, the remaining advanced types: markdown, code, repeater,
 * builder, keyvalue, tags, wizard). The other types ship in
 * FIELDS-ADV-003+.
 */
final class FieldsAdvancedServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-fields-advanced');
    }

    public function packageBooted(): void
    {
        FieldFactory::register('richText', RichTextField::class);
    }
}
