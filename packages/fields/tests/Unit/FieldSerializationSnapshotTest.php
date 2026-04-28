<?php

declare(strict_types=1);

use Arqel\Fields\Field;
use Arqel\Fields\Tests\Fixtures\StubResource;
use Arqel\Fields\Types\BelongsToField;
use Arqel\Fields\Types\BooleanField;
use Arqel\Fields\Types\ColorField;
use Arqel\Fields\Types\CurrencyField;
use Arqel\Fields\Types\DateField;
use Arqel\Fields\Types\DateTimeField;
use Arqel\Fields\Types\EmailField;
use Arqel\Fields\Types\FileField;
use Arqel\Fields\Types\HasManyField;
use Arqel\Fields\Types\HiddenField;
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

/**
 * Snapshot tests for the canonical Field serialisation shape.
 *
 * The shape captured here is the JSON contract the React layer will
 * consume. Each entry serialises a representative configured field
 * and compares against `tests/Snapshots/{type}.json`. On a first run
 * (snapshot missing) the test writes the file and skips; subsequent
 * runs assert byte-equality. To accept an intentional shape change,
 * delete the snapshot file and re-run.
 */
function snapshot(Field $field): array
{
    return [
        'type' => $field->getType(),
        'component' => $field->getComponent(),
        'name' => $field->getName(),
        'label' => $field->getLabel(),
        'readonly' => $field->isReadonly(),
        'placeholder' => $field->getPlaceholder(),
        'helperText' => $field->getHelperText(),
        'defaultValue' => $field->getDefault(),
        'columnSpan' => $field->getColumnSpan(),
        'live' => $field->isLive(),
        'liveDebounce' => $field->getLiveDebounce(),
        'rules' => array_map(
            fn ($r) => is_object($r) ? $r::class : $r,
            $field->getValidationRules(),
        ),
        'props' => $field->getTypeSpecificProps(),
    ];
}

function assertSnapshot(string $name, array $payload): void
{
    $path = __DIR__.'/../Snapshots/'.$name.'.json';
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    expect($json)->not->toBeFalse();

    if (! file_exists($path)) {
        file_put_contents($path, $json.PHP_EOL);
        test()->markTestSkipped("Snapshot {$name} created — re-run to assert.");
    }

    $expected = (string) file_get_contents($path);
    expect(rtrim($json))->toBe(rtrim($expected));
}

dataset('fieldSnapshots', [
    'text' => [fn () => (new TextField('full_name'))->maxLength(255)],
    'textarea' => [fn () => (new TextareaField('bio'))->rows(6)->maxLength(500)],
    'email' => [fn () => new EmailField('email')],
    'url' => [fn () => new UrlField('homepage')],
    'password' => [fn () => (new PasswordField('password'))->revealable()],
    'slug' => [fn () => (new SlugField('slug'))->fromField('title')->reservedSlugs(['admin'])],
    'number' => [fn () => (new NumberField('age'))->min(0)->max(120)->integer()],
    'currency' => [fn () => (new CurrencyField('price'))->prefix('R$')->thousandsSeparator('.')->decimalSeparator(',')],
    'boolean' => [fn () => (new BooleanField('is_active'))->inline()],
    'toggle' => [fn () => (new ToggleField('is_published'))->onColor('emerald')->offColor('zinc')],
    'select' => [fn () => (new SelectField('status'))->options(['draft' => 'Draft', 'published' => 'Published'])],
    'multiSelect' => [fn () => (new MultiSelectField('tags'))->options(['php' => 'PHP', 'js' => 'JS'])],
    'radio' => [fn () => (new RadioField('size'))->options(['s' => 'S', 'l' => 'L'])],
    'belongsTo' => [fn () => BelongsToField::make('author_id', StubResource::class)->preload()],
    'hasMany' => [fn () => HasManyField::make('posts', StubResource::class)],
    'date' => [fn () => (new DateField('birthday'))->minDate('1900-01-01')->timezone('Europe/Lisbon')],
    'dateTime' => [fn () => (new DateTimeField('starts_at'))->seconds()],
    'file' => [fn () => (new FileField('document'))->disk('s3')->maxSize(5120)->acceptedFileTypes(['application/pdf'])],
    'image' => [fn () => (new ImageField('avatar'))->imageCropAspectRatio('1:1')->imageResizeTargetWidth(512)],
    'color' => [fn () => (new ColorField('brand_color'))->presets(['#FF0000', '#00FF00'])->alpha()],
    'hidden' => [fn () => new HiddenField('team_id')],
]);

it('matches the canonical snapshot for each field type', function (Closure $factory): void {
    $field = $factory();
    assertSnapshot($field->getType(), snapshot($field));
})->with('fieldSnapshots');
