<?php

declare(strict_types=1);

namespace Arqel\Ai\Fields;

use Arqel\Ai\AiManager;
use Arqel\Fields\Field;

/**
 * Field para análise de imagem via vision models.
 *
 * `AiImageField` aceita uma imagem (URL ou base64) e dispara N
 * análises configuradas via `aiAnalysis()`. Cada análise roda um
 * prompt-description independente e o resultado é gravado no form
 * field declarado por `populateFields()` (mapeamento
 * `analysis_key => target_form_field`).
 *
 * Exemplo:
 *
 * ```php
 * AiImageField::make('cover')
 *     ->aiAnalysis([
 *         'alt_text' => 'Describe this image in one sentence.',
 *         'tags' => 'Extract 5 SEO tags as comma-separated values.',
 *         'moderation' => 'Is this image appropriate? yes/no',
 *     ])
 *     ->populateFields([
 *         'alt_text' => 'cover_alt',
 *         'tags' => 'cover_tags',
 *     ])
 *     ->provider('claude');
 * ```
 *
 * **Vision support nos providers:** o suporte real a vision (envio
 * da imagem como parte do payload) será adicionado em AI-011
 * follow-up nos `ClaudeProvider` / `OpenAiProvider`. Por agora o
 * field constrói a chamada e repassa a imagem em
 * `options['image']` — providers que não suportem ignoram o option
 * ou retornam erro. O contrato server-side está pronto para receber
 * o suporte vision sem mudança de API pública.
 *
 * Os prompt descriptions ficam estritamente server-side; apenas as
 * `analysis_key`s são expostas via `getTypeSpecificProps()` para o
 * cliente saber quais análises estão disponíveis.
 */
final class AiImageField extends Field
{
    protected string $type = 'aiImage';

    protected string $component = 'AiImageInput';

    /** @var array<string, string> */
    protected array $aiAnalysis = [];

    /** @var array<string, string> */
    protected array $populateFields = [];

    protected ?string $providerName = null;

    /** @var array<string, mixed> */
    protected array $aiOptions = [];

    /** @var array<int, string> */
    protected array $acceptedMimes = ['image/jpeg', 'image/png', 'image/webp'];

    protected int $maxFileSize = 10_485_760;

    protected string $buttonLabel = 'Analyze with AI';

    /**
     * Mapeia `analysis_key => prompt_description`.
     *
     * @param array<string, string> $analyses
     */
    public function aiAnalysis(array $analyses): static
    {
        $this->aiAnalysis = $analyses;

        return $this;
    }

    /**
     * Mapeia `analysis_key => target_form_field`. Cada análise
     * declarada em `aiAnalysis()` é gravada no form field
     * correspondente após `analyze()` (decisão final do populate
     * fica no cliente).
     *
     * @param array<string, string> $mapping
     */
    public function populateFields(array $mapping): static
    {
        $this->populateFields = $mapping;

        return $this;
    }

    public function provider(?string $name): static
    {
        $this->providerName = $name;

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function aiOptions(array $options): static
    {
        $this->aiOptions = $options;

        return $this;
    }

    /**
     * @param array<int, string> $mimes
     */
    public function acceptedMimes(array $mimes): static
    {
        $this->acceptedMimes = array_values($mimes);

        return $this;
    }

    public function maxFileSize(int $bytes): static
    {
        $this->maxFileSize = $bytes;

        return $this;
    }

    public function buttonLabel(string $label): static
    {
        $this->buttonLabel = $label;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getAiAnalysis(): array
    {
        return $this->aiAnalysis;
    }

    /**
     * @return array<string, string>
     */
    public function getPopulateFields(): array
    {
        return $this->populateFields;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    /**
     * @return array<int, string>
     */
    public function getAcceptedMimes(): array
    {
        return $this->acceptedMimes;
    }

    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    public function getButtonLabel(): string
    {
        return $this->buttonLabel;
    }

    /**
     * Roda cada análise declarada em `aiAnalysis()` contra a imagem
     * fornecida. `$imageUrlOrBase64` é qualquer string que o
     * provider vision saiba interpretar — URL absoluta ou data URI
     * base64.
     *
     * O resultado é trim-ed e devolvido como `array<string,string>`
     * com as mesmas keys de `aiAnalysis()`. Falhas individuais por
     * análise propagam — caller deve fazer try/catch se quiser
     * tolerância parcial.
     *
     * @return array<string, string>
     */
    public function analyze(string $imageUrlOrBase64): array
    {
        $manager = app(AiManager::class);
        $results = [];

        $isBase64 = str_starts_with($imageUrlOrBase64, 'data:image');
        $imageOptionKey = $isBase64 ? 'imageBase64' : 'imageUrl';

        foreach ($this->aiAnalysis as $key => $description) {
            $options = $this->aiOptions;
            $options[$imageOptionKey] = $imageUrlOrBase64;
            // Backwards-compat: providers que ainda lêem `image` continuam a funcionar.
            $options['image'] = $imageUrlOrBase64;
            if ($this->providerName !== null && ! isset($options['provider'])) {
                $options['provider'] = $this->providerName;
            }

            $result = $manager->complete($description, $options);
            $results[$key] = trim($result->text);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'analyses' => array_keys($this->aiAnalysis),
            'populateFields' => $this->populateFields,
            'provider' => $this->providerName,
            'acceptedMimes' => $this->acceptedMimes,
            'maxFileSize' => $this->maxFileSize,
            'buttonLabel' => $this->buttonLabel,
        ];
    }
}
