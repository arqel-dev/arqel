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
 * Integration: the default Inertia CRUD save path must feed the
 * Resource's per-field custom messages() and humanised attribute names
 * into the validator. Before the fix, ResourceController::validated()
 * called $request->validate($rules) with RULES ONLY, so `:attribute`
 * rendered the raw snake_case key and any validationMessage() was
 * silently dropped.
 */
final class CustomMessageModel extends Model
{
    protected $guarded = [];
}

final class CustomMessageResource extends Resource
{
    public static string $model = CustomMessageModel::class;

    public static ?string $slug = 'custom-messages';

    public function fields(): array
    {
        return [];
    }

    public function form(): Form
    {
        return Form::make()->schema([
            (new TextField('user_email'))
                ->required()
                ->validationAttribute('endereço de e-mail')
                ->validationMessage('required', 'O campo :attribute é obrigatório.'),
        ]);
    }

    public function runCreate(array $data): Model
    {
        return (new CustomMessageModel)->forceFill(['id' => 1]);
    }
}

function makeCustomMessageController(): ResourceController
{
    $registry = app(ResourceRegistry::class);
    $registry->clear();
    $builder = app(InertiaDataBuilder::class);

    $resource = new CustomMessageResource;
    app()->bind(CustomMessageResource::class, fn () => $resource);
    $registry->register(CustomMessageResource::class);

    return new ResourceController($registry, $builder);
}

it('applies the per-field custom validation message on the default CRUD path', function (): void {
    $controller = makeCustomMessageController();

    $request = Request::create('/custom-messages', 'POST', ['resource' => 'custom-messages']);

    try {
        $controller->store($request, 'custom-messages');
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $e) {
        $messages = $e->validator->errors()->get('user_email');

        // Custom message text wins...
        expect($messages)->toContain('O campo endereço de e-mail é obrigatório.')
            // ...and the raw snake_case fallback never surfaces.
            ->and($messages)->not->toContain('The user email field is required.');
    }
});

it('substitutes the humanised attribute name into a default message', function (): void {
    // A field with a custom attribute but NO custom message: Laravel's
    // own translated message must interpolate the humanised attribute,
    // proving extractAttributes() reached the validator.
    $registry = app(ResourceRegistry::class);
    $registry->clear();
    $builder = app(InertiaDataBuilder::class);

    $resource = new class extends Resource
    {
        public static string $model = CustomMessageModel::class;

        public static ?string $slug = 'attr-only';

        public function fields(): array
        {
            return [];
        }

        public function form(): Form
        {
            return Form::make()->schema([
                (new TextField('user_email'))
                    ->required()
                    ->validationAttribute('e-mail address'),
            ]);
        }

        public function runCreate(array $data): Model
        {
            return (new CustomMessageModel)->forceFill(['id' => 1]);
        }
    };

    app()->bind($resource::class, fn () => $resource);
    $registry->register($resource::class);

    $controller = new ResourceController($registry, $builder);
    $request = Request::create('/attr-only', 'POST', ['resource' => 'attr-only']);

    try {
        $controller->store($request, 'attr-only');
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $e) {
        $message = $e->validator->errors()->first('user_email');

        expect($message)->toContain('e-mail address')
            ->and($message)->not->toContain('user_email');
    }
});
