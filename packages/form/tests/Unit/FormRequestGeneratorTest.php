<?php

declare(strict_types=1);

use Arqel\Form\FormRequestGenerator;

/**
 * Hand-rolled fixture used by the generator tests so we don't
 * pull `arqel/core` Resource into form's unit tests. The generator
 * only relies on `class_exists` + class-basename derivation.
 */
final class StubArticleResource
{
    public static function getModel(): string
    {
        return 'App\\Models\\Article';
    }
}

beforeEach(function (): void {
    $this->generator = new FormRequestGenerator;
    $this->tmp = sys_get_temp_dir().'/arqel-form-request-test-'.uniqid();
    mkdir($this->tmp, 0o755, true);
});

afterEach(function (): void {
    if (is_dir($this->tmp)) {
        foreach (glob($this->tmp.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tmp);
    }
});

it('generates a Store request with the expected class name and ability', function (): void {
    $output = $this->generator->generate(StubArticleResource::class, FormRequestGenerator::ACTION_STORE);

    expect($output)
        ->toContain('final class StoreStubArticleRequest extends FormRequest')
        ->toContain("Gate::allows('create',")
        ->toContain('use Arqel\\Form\\FieldRulesExtractor;');
});

it('generates an Update request with the update ability', function (): void {
    $output = $this->generator->generate(StubArticleResource::class, FormRequestGenerator::ACTION_UPDATE);

    expect($output)
        ->toContain('final class UpdateStubArticleRequest extends FormRequest')
        ->toContain("Gate::allows('update',");
});

it('falls back to store when an unknown action is passed', function (): void {
    $output = $this->generator->generate(StubArticleResource::class, 'destroy');

    expect($output)->toContain('class StoreStubArticleRequest');
});

it('throws when the resource class does not exist', function (): void {
    $this->generator->generate('App\\NotARealClass');
})->throws(RuntimeException::class);

it('write() emits both Store and Update files in the target path', function (): void {
    $written = $this->generator->write(StubArticleResource::class, $this->tmp);

    expect($written)->toHaveCount(2)
        ->and(file_exists($this->tmp.'/StoreStubArticleRequest.php'))->toBeTrue()
        ->and(file_exists($this->tmp.'/UpdateStubArticleRequest.php'))->toBeTrue();
});

it('write() skips existing files unless --force is set', function (): void {
    $this->generator->write(StubArticleResource::class, $this->tmp);
    $second = $this->generator->write(StubArticleResource::class, $this->tmp);

    expect($second)->toBe([]);

    $third = $this->generator->write(StubArticleResource::class, $this->tmp, force: true);
    expect($third)->toHaveCount(2);
});
