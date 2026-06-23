<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validação de submissão de plugin (MKTPLC-002).
 *
 * Slug é derivado de `name` via `Str::slug` quando ausente, e a unicidade é
 * verificada contra a tabela `arqel_plugins`.
 */
final class SubmitPluginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        /** @var mixed $slug */
        $slug = $this->input('slug');
        /** @var mixed $name */
        $name = $this->input('name');

        if (! is_string($slug) || trim($slug) === '') {
            if (is_string($name) && trim($name) !== '') {
                $this->merge(['slug' => Str::slug($name)]);
            }
        }
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'min:3', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:arqel_plugins,slug'],
            'composer_package' => ['required', 'string', 'regex:/^[a-z0-9-]+\/[a-z0-9-]+$/'],
            'npm_package' => ['nullable', 'string', 'max:200'],
            'github_url' => ['required', 'url'],
            'type' => ['required', 'in:field,widget,integration,theme'],
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'screenshots' => ['nullable', 'array'],
            'screenshots.*' => ['url'],
            'license' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Localized rule messages for the cases where Laravel's defaults are too
     * generic (regex/in). Resolved lazily at validation time so the request
     * locale applies.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => __('arqel::arqel.marketplace.messages.slug_regex'),
            'composer_package.regex' => __('arqel::arqel.marketplace.messages.composer_package_regex'),
            'type.in' => __('arqel::arqel.marketplace.messages.type_in'),
        ];
    }

    /**
     * Human-readable, localized field names so :attribute placeholders no
     * longer render raw snake_case identifiers in error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'slug' => __('arqel::arqel.marketplace.attributes.slug'),
            'composer_package' => __('arqel::arqel.marketplace.attributes.composer_package'),
            'npm_package' => __('arqel::arqel.marketplace.attributes.npm_package'),
            'github_url' => __('arqel::arqel.marketplace.attributes.github_url'),
            'type' => __('arqel::arqel.marketplace.attributes.type'),
            'name' => __('arqel::arqel.marketplace.attributes.name'),
            'description' => __('arqel::arqel.marketplace.attributes.description'),
            'screenshots' => __('arqel::arqel.marketplace.attributes.screenshots'),
            'license' => __('arqel::arqel.marketplace.attributes.license'),
        ];
    }
}
