<?php

declare(strict_types=1);

namespace Arqel\Ai\Fields;

use Arqel\Ai\AiManager;
use Arqel\Fields\Field;
use InvalidArgumentException;

/**
 * Field para conteúdo multi-idioma com tradução automática via AI.
 *
 * Ao contrário de `AiTextField` (que produz **um** texto), o
 * `AiTranslateField` armazena um array `{languageCode => text}` e
 * permite preencher idiomas ausentes a partir do `defaultLanguage`
 * usando o provider configurado.
 *
 * O persistir esperado em user-land é uma coluna JSON — recomenda-se
 * declarar `protected function casts(): array { return ['<column>' =>
 * 'array']; }` (ou `'json'`) no model. O field não força o cast
 * automaticamente; cabe ao app definir a estratégia (Eloquent JSON
 * cast, Spatie Translatable, etc.).
 *
 * O prompt usado pela tradução é construído server-side dentro de
 * `translate()` — nunca é exposto para o cliente. A camada React
 * apenas dispara `POST /admin/{resource}/fields/{field}/translate`
 * informando idioma de origem e idiomas-alvo.
 */
final class AiTranslateField extends Field
{
    protected string $type = 'aiTranslate';

    protected string $component = 'AiTranslateInput';

    /** @var array<int, string> */
    protected array $languages = [];

    protected ?string $defaultLanguage = null;

    protected bool $autoTranslate = false;

    protected ?string $providerName = null;

    /** @var array<string, mixed> */
    protected array $aiOptions = [];

    /**
     * Lista de language codes suportados (`['en', 'pt-BR', 'es']`).
     *
     * @param array<int, string> $codes
     */
    public function languages(array $codes): static
    {
        $this->languages = array_values($codes);

        return $this;
    }

    /**
     * Idioma-base usado como fonte das traduções automáticas.
     *
     * @throws InvalidArgumentException quando `$code` não está em `languages()`
     */
    public function defaultLanguage(string $code): static
    {
        if (! in_array($code, $this->languages, true)) {
            throw new InvalidArgumentException(
                "Default language [{$code}] must be one of the configured languages: ".
                implode(', ', $this->languages).'.',
            );
        }

        $this->defaultLanguage = $code;

        return $this;
    }

    public function autoTranslate(bool $auto = true): static
    {
        $this->autoTranslate = $auto;

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
     * Opções extra repassadas a `AiManager::complete()` (e.g. `temperature`,
     * `model`, `max_tokens`).
     *
     * @param array<string, mixed> $options
     */
    public function aiOptions(array $options): static
    {
        $this->aiOptions = $options;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getLanguages(): array
    {
        return $this->languages;
    }

    public function getDefaultLanguage(): ?string
    {
        return $this->defaultLanguage;
    }

    public function isAutoTranslate(): bool
    {
        return $this->autoTranslate;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    /**
     * Traduz `$sourceText` para `$targetLanguage`. Quando informado,
     * `$sourceLanguage` aparece no prompt para reduzir ambiguidade
     * (e.g. distinguir `en` de `pt-BR` em textos bilíngues).
     */
    public function translate(string $sourceText, string $targetLanguage, ?string $sourceLanguage = null): string
    {
        $from = $sourceLanguage ?? 'the source language';

        $prompt = "Translate the following text from {$from} to {$targetLanguage}. ".
            "Return only the translated text, nothing else.\n\n".
            $sourceText;

        $options = $this->aiOptions;
        if ($this->providerName !== null && ! isset($options['provider'])) {
            $options['provider'] = $this->providerName;
        }

        $manager = app(AiManager::class);
        $result = $manager->complete($prompt, $options);

        return $result->text;
    }

    /**
     * Preenche o array `{lang => text}` com traduções para todos os
     * `languages()` ausentes ou vazios. O `$sourceLanguage` é pulado
     * (a fonte sempre vem do user-land). Idiomas já preenchidos
     * mantêm o valor existente — `translateAll` nunca sobrescreve
     * traduções manuais.
     *
     * @param array<string, string> $translations
     *
     * @return array<string, string>
     */
    public function translateAll(array $translations, string $sourceLanguage): array
    {
        $sourceText = $translations[$sourceLanguage] ?? '';

        foreach ($this->languages as $language) {
            if ($language === $sourceLanguage) {
                continue;
            }

            $existing = $translations[$language] ?? '';
            if ($existing !== '') {
                continue;
            }

            $translations[$language] = $this->translate($sourceText, $language, $sourceLanguage);
        }

        return $translations;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'languages' => $this->languages,
            'defaultLanguage' => $this->defaultLanguage,
            'autoTranslate' => $this->autoTranslate,
            'provider' => $this->providerName,
        ];
    }
}
