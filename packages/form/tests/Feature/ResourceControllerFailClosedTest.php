<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\ResourceController;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Security regression (FORM-007 fail-closed): the real
 * `Arqel\Form\FieldRulesExtractor` IS installed in this package's test
 * bench (core + form providers both booted). When rule extraction blows
 * up for an infrastructure reason, the controller must propagate the
 * failure (HTTP 500) instead of silently falling back to the permissive
 * path that strips params and accepts unvalidated input — which would be
 * a mass-assignment hole.
 *
 * We drive that by giving a Resource whose `fields()` throws: the
 * extractor never gets to run cleanly, the controller's try/catch
 * re-raises a RuntimeException, and `runCreate` is never reached.
 */
final class ExplodingFieldsModel extends Model
{
    protected $guarded = [];
}

final class ExplodingFieldsResource extends Resource
{
    public static string $model = ExplodingFieldsModel::class;

    public static ?string $slug = 'exploders';

    public bool $runCreateCalled = false;

    public function fields(): array
    {
        throw new RuntimeException('boom: fields() is broken');
    }

    public function runCreate(array $data): Model
    {
        $this->runCreateCalled = true;

        return new ExplodingFieldsModel;
    }
}

it('fails closed when rule extraction throws, never accepting unvalidated input', function (): void {
    expect(class_exists(Arqel\Form\FieldRulesExtractor::class))->toBeTrue();

    $registry = app(ResourceRegistry::class);
    $registry->clear();
    $builder = app(InertiaDataBuilder::class);

    $resource = new ExplodingFieldsResource;
    app()->bind(ExplodingFieldsResource::class, fn () => $resource);
    $registry->register(ExplodingFieldsResource::class);

    $controller = new ResourceController($registry, $builder);

    $request = Request::create('/exploders', 'POST', [
        'resource' => 'exploders',
        'name' => 'Alice',
        'is_admin' => true, // the kind of field a mass-assignment hole would let through
    ]);

    expect(fn () => $controller->store($request, 'exploders'))
        ->toThrow(RuntimeException::class);

    expect($resource->runCreateCalled)->toBeFalse();
});
