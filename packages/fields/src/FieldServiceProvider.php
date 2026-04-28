<?php

declare(strict_types=1);

namespace Arqel\Fields;

use Arqel\Fields\Types\BelongsToField;
use Arqel\Fields\Types\BooleanField;
use Arqel\Fields\Types\CurrencyField;
use Arqel\Fields\Types\DateField;
use Arqel\Fields\Types\DateTimeField;
use Arqel\Fields\Types\EmailField;
use Arqel\Fields\Types\FileField;
use Arqel\Fields\Types\HasManyField;
use Arqel\Fields\Types\ImageField;
use Arqel\Fields\Types\MultiSelectField;
use Arqel\Fields\Types\NumberField;
use Arqel\Fields\Types\PasswordField;
use Arqel\Fields\Types\RadioField;
use Arqel\Fields\Types\SelectField;
use Arqel\Fields\Types\SlugField;
use Arqel\Fields\Types\TextareaField;
use Arqel\Fields\Types\TextField;
use Arqel\Fields\Types\ToggleField;
use Arqel\Fields\Types\UrlField;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for `arqel/fields`.
 *
 * Registers the package with Spatie's package tools and binds the
 * built-in field types into the `FieldFactory` so apps can call
 * `FieldFactory::text(...)`, `FieldFactory::email(...)`, etc.
 */
final class FieldServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-fields');
    }

    public function packageBooted(): void
    {
        FieldFactory::register('text', TextField::class);
        FieldFactory::register('textarea', TextareaField::class);
        FieldFactory::register('email', EmailField::class);
        FieldFactory::register('url', UrlField::class);
        FieldFactory::register('password', PasswordField::class);
        FieldFactory::register('slug', SlugField::class);
        FieldFactory::register('number', NumberField::class);
        FieldFactory::register('currency', CurrencyField::class);
        FieldFactory::register('boolean', BooleanField::class);
        FieldFactory::register('toggle', ToggleField::class);
        FieldFactory::register('select', SelectField::class);
        FieldFactory::register('multiSelect', MultiSelectField::class);
        FieldFactory::register('radio', RadioField::class);
        FieldFactory::register('belongsTo', BelongsToField::class);
        FieldFactory::register('hasMany', HasManyField::class);
        FieldFactory::register('date', DateField::class);
        FieldFactory::register('dateTime', DateTimeField::class);
        FieldFactory::register('file', FileField::class);
        FieldFactory::register('image', ImageField::class);
    }
}
