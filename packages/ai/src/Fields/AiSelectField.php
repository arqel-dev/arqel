<?php

declare(strict_types=1);

namespace Arqel\Ai\Fields;

use Arqel\Ai\AiManager;
use Arqel\Fields\Field;
use Closure;

/**
 * Field que usa AI para classificar dados do form em uma das `options`
 * configuradas. Diferente de um `Select` tradicional, o valor pode ser
 * inferido server-side a partir de outros campos via `classify()`.
 *
 * O contrato é simples: o consumidor declara o conjunto fechado de
 * `options` (`{key => label}`), o template de prompt e a lista de
 * campos de contexto. Quando o cliente dispara
 * `POST /admin/{resource}/fields/{field}/classify`, o backend resolve
 * o prompt, anexa a lista de categorias disponíveis, chama o provider
 * e retorna a `key` escolhida (após normalização e validação contra
 * `options`).
 *
 * Quando a AI devolve uma resposta inválida (key fora do set), o
 * comportamento é determinado por `fallbackOption()`:
 * - `null` (default): `classify()` devolve `null` — o React deixa o
 *   select sem valor selecionado.
 * - `string`: a key fornecida é usada como fallback determinístico.
 *
 * O prompt template **nunca** trafega para o cliente (consistente com
 * AI-007/AI-008).
 */
final class AiSelectField extends Field
{
    protected string $type = 'aiSelect';

    protected string $component = 'AiSelectInput';

    /** @var array<string, string> */
    protected array $options = [];

    /** @var array<int, string> */
    protected array $classifyFromFields = [];

    protected string|Closure|null $aiPrompt = null;

    protected ?string $providerName = null;

    /** @var array<string, mixed> */
    protected array $aiOptions = [];

    protected ?string $fallbackOption = null;

    /**
     * Conjunto fechado de opções no formato `{key => label}`. A AI deve
     * retornar uma das `keys`; respostas fora deste set caem no
     * `fallbackOption`.
     *
     * @param array<string, string> $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Nome dos campos do form cujos valores podem aparecer como
     * `{name}` no prompt template.
     *
     * @param array<int, string> $fieldNames
     */
    public function classifyFromFields(array $fieldNames): static
    {
        $this->classifyFromFields = array_values($fieldNames);

        return $this;
    }

    /**
     * Define o prompt template — string com placeholders `{fieldName}`
     * ou closure recebendo `array $formData` e retornando string.
     */
    public function prompt(string|Closure $prompt): static
    {
        $this->aiPrompt = $prompt;

        return $this;
    }

    /**
     * Override do provider configurado em `arqel-ai.default_provider`.
     */
    public function provider(?string $name): static
    {
        $this->providerName = $name;

        return $this;
    }

    /**
     * Opções extra repassadas a `AiManager::complete()`.
     *
     * @param array<string, mixed> $options
     */
    public function aiOptions(array $options): static
    {
        $this->aiOptions = $options;

        return $this;
    }

    /**
     * Option key usada quando a AI retorna uma key inválida. `null`
     * (default) faz `classify()` devolver `null` em vez de erro.
     */
    public function fallbackOption(?string $key): static
    {
        $this->fallbackOption = $key;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return array<int, string>
     */
    public function getClassifyFromFields(): array
    {
        return $this->classifyFromFields;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    public function getFallbackOption(): ?string
    {
        return $this->fallbackOption;
    }

    /**
     * Resolve o prompt template substituindo placeholders `{fieldName}`
     * pelos valores presentes em `$formData`.
     *
     * @param array<string, mixed> $formData
     */
    private function resolvePrompt(array $formData): string
    {
        $prompt = $this->aiPrompt;

        if ($prompt instanceof Closure) {
            $result = ($prompt)($formData);

            return is_string($result) ? $result : (is_scalar($result) ? (string) $result : (string) json_encode($result));
        }

        if ($prompt === null) {
            return '';
        }

        $resolved = $prompt;
        foreach ($formData as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $replacement = is_scalar($value) || $value === null
                ? (string) $value
                : (string) json_encode($value);

            $resolved = str_replace('{'.$key.'}', $replacement, $resolved);
        }

        return $resolved;
    }

    /**
     * Classifica `$formData` retornando a key escolhida pela AI. Quando
     * a resposta não bate com nenhuma key de `options`, devolve
     * `fallbackOption()` (que pode ser `null`).
     *
     * @param array<string, mixed> $formData
     */
    public function classify(array $formData): ?string
    {
        $userPrompt = $this->resolvePrompt($formData);

        $categoriesList = '';
        foreach ($this->options as $key => $label) {
            $categoriesList .= "- {$key}: {$label}\n";
        }

        $fullPrompt = $userPrompt
            ."\n\nAvailable categories (key: label):\n"
            .$categoriesList
            ."\nReply with ONLY the category key, nothing else.";

        $options = $this->aiOptions;
        if ($this->providerName !== null && ! isset($options['provider'])) {
            $options['provider'] = $this->providerName;
        }

        $manager = app(AiManager::class);
        $result = $manager->complete($fullPrompt, $options);

        $candidate = $this->normalize($result->text);

        if ($candidate !== '' && array_key_exists($candidate, $this->options)) {
            return $candidate;
        }

        return $this->fallbackOption;
    }

    /**
     * Limpa o output da AI: trim, lowercase e strip de aspas/pontuação
     * de borda comuns. Não tenta interpretar respostas multi-palavra —
     * keys do select são tokens curtos.
     */
    private function normalize(string $raw): string
    {
        $cleaned = trim($raw);
        $cleaned = trim($cleaned, "\"'`.,;:!? \t\n\r\0\x0B");

        return mb_strtolower($cleaned);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'options' => $this->options,
            'classifyFromFields' => $this->classifyFromFields,
            'provider' => $this->providerName,
            'fallbackOption' => $this->fallbackOption,
            'hasContextFields' => count($this->classifyFromFields) > 0,
        ];
    }
}
