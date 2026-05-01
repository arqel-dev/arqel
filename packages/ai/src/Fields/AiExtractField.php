<?php

declare(strict_types=1);

namespace Arqel\Ai\Fields;

use Arqel\Ai\AiManager;
use Arqel\Ai\Exceptions\AiException;
use Arqel\Fields\Field;

/**
 * Field que extrai dados estruturados de texto livre via AI. O
 * consumidor declara um `extractTo` mapeando `targetField =>
 * description`; ao chamar `extract($sourceText)`, o backend constrói
 * um prompt server-side, opcionalmente usa JSON mode (`json_mode =>
 * true`, suportado pelo OpenAI; outros providers ignoram graciosamente)
 * e devolve um array `{targetField => value}` com **apenas** as keys
 * declaradas em `extractTo` (extras vindos da AI são filtrados; keys
 * ausentes são preenchidas com `null` para o consumidor distinguir
 * "AI omitiu" de "key não esperada").
 *
 * Quando o output da AI contém prosa antes/depois do JSON, há um
 * fallback regex que tenta isolar o primeiro `{ ... }` e re-decode.
 * Falha total nesse pipeline lança `AiException`.
 *
 * O `extractTo` (com descriptions) **nunca** é exposto via
 * `getTypeSpecificProps()` — descriptions são parte do prompt e
 * podem conter regras de negócio. O cliente recebe apenas os nomes
 * das keys-alvo.
 */
final class AiExtractField extends Field
{
    protected string $type = 'aiExtract';

    protected string $component = 'AiExtractInput';

    protected ?string $sourceField = null;

    /** @var array<string, string> */
    protected array $extractTo = [];

    protected bool $jsonMode = false;

    protected ?string $providerName = null;

    /** @var array<string, mixed> */
    protected array $aiOptions = [];

    protected string $buttonLabel = 'Extract with AI';

    /**
     * Nome do campo do form que contém o texto-fonte (e.g. `'raw_text'`).
     */
    public function sourceField(string $fieldName): static
    {
        $this->sourceField = $fieldName;

        return $this;
    }

    /**
     * Mapa `targetField => description` declarando o schema da extração.
     * Descriptions vão para o prompt e **não** trafegam para o cliente.
     *
     * @param array<string, string> $schema
     */
    public function extractTo(array $schema): static
    {
        $this->extractTo = $schema;

        return $this;
    }

    /**
     * Quando `true`, repassa `['json_mode' => true]` ao provider.
     * Suportado pelo OpenAI; outros providers ignoram graciosamente.
     */
    public function usingJsonMode(bool $on = true): static
    {
        $this->jsonMode = $on;

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
     * Opções extra repassadas a `AiManager::complete()` (e.g.
     * `temperature`, `model`, `max_tokens`).
     *
     * @param array<string, mixed> $options
     */
    public function aiOptions(array $options): static
    {
        $this->aiOptions = $options;

        return $this;
    }

    public function buttonLabel(string $label): static
    {
        $this->buttonLabel = $label;

        return $this;
    }

    public function getSourceField(): ?string
    {
        return $this->sourceField;
    }

    /**
     * @return array<string, string>
     */
    public function getExtractTo(): array
    {
        return $this->extractTo;
    }

    public function isUsingJsonMode(): bool
    {
        return $this->jsonMode;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    public function getButtonLabel(): string
    {
        return $this->buttonLabel;
    }

    /**
     * Extrai dados estruturados de `$sourceText`. Retorna um array
     * com **apenas** as keys declaradas em `extractTo()`; keys
     * ausentes na resposta da AI são preenchidas com `null`.
     *
     * @return array<string, mixed>
     *
     * @throws AiException quando a resposta da AI não é JSON válido
     *                     mesmo após o fallback regex
     */
    public function extract(string $sourceText): array
    {
        $fieldsBlock = '';
        foreach ($this->extractTo as $key => $description) {
            $fieldsBlock .= "- {$key}: {$description}\n";
        }

        $prompt = "Extract the following structured data from the text below.\n\n"
            ."Fields:\n"
            .$fieldsBlock
            ."\nRespond with a JSON object with EXACTLY these keys. "
            ."No prose, no explanation, just the JSON.\n\n"
            ."Text:\n"
            .$sourceText;

        $options = array_merge(
            $this->jsonMode ? ['json_mode' => true] : [],
            $this->aiOptions,
        );
        if ($this->providerName !== null && ! isset($options['provider'])) {
            $options['provider'] = $this->providerName;
        }

        $manager = app(AiManager::class);
        $result = $manager->complete($prompt, $options);

        $decoded = $this->decodeJson($result->text);

        $output = [];
        foreach (array_keys($this->extractTo) as $targetKey) {
            $output[$targetKey] = array_key_exists($targetKey, $decoded) ? $decoded[$targetKey] : null;
        }

        return $output;
    }

    /**
     * Faz `json_decode` resiliente: tenta decode direto; se falhar,
     * isola o primeiro `{ ... }` via regex e tenta de novo. Lança
     * `AiException` quando ambas falham.
     *
     * @return array<string, mixed>
     */
    private function decodeJson(string $raw): array
    {
        $attempt = json_decode($raw, true);
        if (is_array($attempt)) {
            /** @var array<string, mixed> $attempt */
            return $attempt;
        }

        if (preg_match('/\{[\s\S]*\}/', $raw, $matches) === 1) {
            $fallback = json_decode($matches[0], true);
            if (is_array($fallback)) {
                /** @var array<string, mixed> $fallback */
                return $fallback;
            }
        }

        $prefix = mb_substr($raw, 0, 120);

        throw new AiException("Failed to parse AI response as JSON: {$prefix}");
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'sourceField' => $this->sourceField,
            'targetFields' => array_keys($this->extractTo),
            'buttonLabel' => $this->buttonLabel,
            'usingJsonMode' => $this->jsonMode,
            'provider' => $this->providerName,
        ];
    }
}
