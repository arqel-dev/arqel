<?php

declare(strict_types=1);

namespace Arqel\Ai\Fields;

use Arqel\Ai\AiManager;
use Arqel\Fields\Types\TextField;
use Closure;

/**
 * Field que gera texto via AI a partir de um prompt template.
 *
 * Estende `TextareaField` (multi-linha por default) e adiciona setters
 * para o prompt template, provider, opções de chamada, lista de campos
 * cujos valores devem ser interpolados via placeholders `{fieldName}`,
 * limite máximo de caracteres do output e label do botão de geração.
 *
 * O prompt é resolvido server-side dentro de `generate()` — o cliente
 * apenas dispara a chamada via `AiGenerateController` enviando o
 * `formData` atual, e o backend retorna o texto pronto. O prompt
 * template **nunca** trafega para o cliente (segurança/IP).
 */
final class AiTextField extends TextField
{
    protected string $type = 'aiText';

    protected string $component = 'AiTextInput';

    protected string|Closure|null $aiPrompt = null;

    protected ?string $providerName = null;

    /** @var array<string, mixed> */
    protected array $aiOptions = [];

    /** @var array<int, string> */
    protected array $contextFields = [];

    protected string $buttonLabel = 'Generate with AI';

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
     * Opções extra repassadas a `AiManager::complete()` (e.g. `max_tokens`,
     * `temperature`, `model`).
     *
     * @param array<string, mixed> $options
     */
    public function aiOptions(array $options): static
    {
        $this->aiOptions = $options;

        return $this;
    }

    /**
     * Nome dos campos do form cujo valor pode aparecer como `{name}`
     * no prompt template — usado também no React para invalidar
     * o botão até que esses campos estejam preenchidos.
     *
     * @param array<int, string> $fieldNames
     */
    public function contextFields(array $fieldNames): static
    {
        $this->contextFields = array_values($fieldNames);

        return $this;
    }

    public function buttonLabel(string $label): static
    {
        $this->buttonLabel = $label;

        return $this;
    }

    public function getButtonLabel(): string
    {
        return $this->buttonLabel;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    /**
     * @return array<int, string>
     */
    public function getContextFields(): array
    {
        return $this->contextFields;
    }

    /**
     * Resolve o prompt template substituindo placeholders `{fieldName}`
     * pelos valores presentes em `$formData`. Closures recebem o
     * `$formData` inteiro e devem retornar a string final.
     *
     * @param array<string, mixed> $formData
     */
    public function resolvePrompt(array $formData): string
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
     * Gera o texto chamando `AiManager` com o prompt resolvido. Se o
     * resultado exceder `$maxLength` (quando configurado), trunca em
     * `maxLength - 1` caracteres e adiciona `…`.
     *
     * @param array<string, mixed> $formData
     */
    public function generate(array $formData): string
    {
        $prompt = $this->resolvePrompt($formData);

        $options = $this->aiOptions;
        if ($this->providerName !== null && ! isset($options['provider'])) {
            $options['provider'] = $this->providerName;
        }

        $manager = app(AiManager::class);
        $result = $manager->complete($prompt, $options);

        $text = $result->text;

        if ($this->maxLength !== null && mb_strlen($text) > $this->maxLength) {
            $text = mb_substr($text, 0, max(0, $this->maxLength - 1)).'…';
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            ...parent::getTypeSpecificProps(),
            'provider' => $this->providerName,
            'buttonLabel' => $this->buttonLabel,
            'maxLength' => $this->maxLength,
            'hasContextFields' => count($this->contextFields) > 0,
            'contextFields' => $this->contextFields !== [] ? $this->contextFields : null,
        ], fn ($value) => $value !== null);
    }
}
