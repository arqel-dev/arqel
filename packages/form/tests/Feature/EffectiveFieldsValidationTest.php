<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\ResourceController;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Integration: with the real FieldRulesExtractor (core + form booted),
 * a Resource whose required field lives ONLY in form() — not in the flat
 * fields() — must still be validated. This proves ResourceController
 * sources rules from effectiveFields() (which prefers form()->getFields())
 * rather than fields(). Before the effectiveFields() change the controller
 * validated fields() and the form-only field went unchecked.
 */
final class FormOnlyFieldModel extends Model
{
    protected $guarded = [];
}

final class FormOnlyFieldResource extends Resource
{
    public static string $model = FormOnlyFieldModel::class;

    public static ?string $slug = 'form-only';

    public bool $runCreateCalled = false;

    // Flat fields() declares NOTHING — the real field list is in form().
    public function fields(): array
    {
        return [];
    }

    public function form(): Form
    {
        return Form::make()->schema([
            (new TextField('title'))->required(),
        ]);
    }

    public function runCreate(array $data): Model
    {
        $this->runCreateCalled = true;

        // Bare model with a key so the controller's redirect to
        // arqel.resources.edit can be built without touching the DB.
        return (new FormOnlyFieldModel)->forceFill(['id' => 1]);
    }
}

it('validates a required field declared only in form(), not fields()', function (): void {
    $registry = app(ResourceRegistry::class);
    $registry->clear();
    $builder = app(InertiaDataBuilder::class);

    $resource = new FormOnlyFieldResource;
    app()->bind(FormOnlyFieldResource::class, fn () => $resource);
    $registry->register(FormOnlyFieldResource::class);

    $controller = new ResourceController($registry, $builder);

    // POST with the required 'title' MISSING -> validation must fail.
    $request = Request::create('/form-only', 'POST', ['resource' => 'form-only']);

    expect(fn () => $controller->store($request, 'form-only'))
        ->toThrow(ValidationException::class);

    expect($resource->runCreateCalled)->toBeFalse();
});

it('passes validation when the form-only required field is present', function (): void {
    $registry = app(ResourceRegistry::class);
    $registry->clear();
    $builder = app(InertiaDataBuilder::class);

    $resource = new FormOnlyFieldResource;
    app()->bind(FormOnlyFieldResource::class, fn () => $resource);
    $registry->register(FormOnlyFieldResource::class);

    $controller = new ResourceController($registry, $builder);

    $request = Request::create('/form-only', 'POST', [
        'resource' => 'form-only',
        'title' => 'Hello',
    ]);

    $response = $controller->store($request, 'form-only');

    expect($resource->runCreateCalled)->toBeTrue()
        ->and($response->getStatusCode())->toBe(302);
});
