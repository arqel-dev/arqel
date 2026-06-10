<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;
use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\FormRequestGenerator;
use Illuminate\Database\Eloquent\Model;

/**
 * Security regression (#241): the GENERATED FormRequest must source its
 * rules from the same place the runtime ResourceController does —
 * `effectiveFields()` (which prefers `form()->getFields()`), not the flat
 * `fields()`. A Resource that hardens a field only in `form()` (e.g.
 * `->required()`) used to slip through the generated request unvalidated,
 * because the stub called `fields()` (which omits it) → mass-assignment.
 *
 * This mirrors the showcase PostResource divergence: a required field
 * declared in form() but absent from fields().
 */
final class GeneratedReqModel extends Model
{
    protected $guarded = [];
}

final class GeneratedReqPostResource extends Resource
{
    public static string $model = GeneratedReqModel::class;

    public static ?string $slug = 'generated-req-post';

    // Flat fields() omits author_id entirely.
    public function fields(): array
    {
        return [
            new TextField('title'),
        ];
    }

    // author_id is hardened (required) ONLY here, in form().
    public function form(): Form
    {
        return Form::make()->schema([
            (new TextField('title')),
            (new TextField('author_id'))->required(),
        ]);
    }
}

it('generates a FormRequest whose rules() includes a required field declared only in form()', function (): void {
    $resource = new GeneratedReqPostResource;
    app()->bind(GeneratedReqPostResource::class, fn () => $resource);

    $generator = new FormRequestGenerator;
    $namespace = 'Arqel\\Form\\Tests\\Generated';
    $source = $generator->generate(
        GeneratedReqPostResource::class,
        FormRequestGenerator::ACTION_STORE,
        $namespace,
    );

    // Materialise + load the generated class so we exercise its real
    // rules() body (not just a string match on the template).
    $tmp = tempnam(sys_get_temp_dir(), 'arqel-genreq-').'.php';
    file_put_contents($tmp, $source);

    try {
        require $tmp;

        $requestClass = $namespace.'\\StoreGeneratedReqPostRequest';
        /** @var Illuminate\Foundation\Http\FormRequest $request */
        $request = new $requestClass;
        $request->setContainer(app());

        $rules = $request->rules();

        // The form-only required field must be validated.
        expect($rules)->toHaveKey('author_id');
        expect($rules['author_id'])->toContain('required');

        // And the flat-fields field is still present.
        expect($rules)->toHaveKey('title');
    } finally {
        @unlink($tmp);
    }
});
