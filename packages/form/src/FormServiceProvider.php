<?php

declare(strict_types=1);

namespace Arqel\Form;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for `arqel/form`.
 *
 * Today the provider is the boot anchor; the `Form` builder,
 * layout components, and FormRequest generator land in
 * FORM-002 onwards.
 */
final class FormServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-form');
    }
}
