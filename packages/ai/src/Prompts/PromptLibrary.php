<?php

declare(strict_types=1);

namespace Arqel\Ai\Prompts;

use Closure;
use InvalidArgumentException;

/**
 * Biblioteca reutilizável de prompt templates.
 *
 * Os métodos estáticos retornam strings determinísticas — não invocam o AI
 * provider. Ficam server-side: o caller passa o resultado para
 * `AiManager::complete()` (ou equivalente) quando quiser executá-los.
 *
 * Custom prompts podem ser registrados em runtime via `register()`. O closure
 * recebe um `array<string,mixed>` e retorna a string final do prompt.
 */
final class PromptLibrary
{
    /**
     * @var array<string, Closure(array<string,mixed>):string>
     */
    private static array $custom = [];

    /**
     * Resume um texto em até `$maxWords` palavras.
     */
    public static function summarize(string $text, int $maxWords = 100): string
    {
        return "Summarize the following text in at most {$maxWords} words. "
            ."Return only the summary, no preamble or explanations.\n\n"
            ."Text:\n{$text}";
    }

    /**
     * Traduz `$text` para `$targetLanguage`. Quando `$sourceLanguage` é
     * informado, o prompt explicita o par de idiomas.
     */
    public static function translate(string $text, string $targetLanguage, ?string $sourceLanguage = null): string
    {
        $direction = $sourceLanguage !== null && $sourceLanguage !== ''
            ? "from {$sourceLanguage} to {$targetLanguage}"
            : "to {$targetLanguage}";

        return "Translate the following text {$direction}. "
            ."Return only the translation, preserving formatting and tone, no explanations.\n\n"
            ."Text:\n{$text}";
    }

    /**
     * Classifica `$text` em uma das categorias dadas.
     *
     * `$categories` aceita lista simples (`['tech','finance']`) ou mapa
     * `key => label` (`['tech' => 'Technology']`). Em ambos os casos, o modelo
     * é instruído a responder com a **key** (ou o valor, no formato lista).
     *
     * @param array<int|string, string> $categories
     */
    public static function classify(string $text, array $categories): string
    {
        $isAssoc = array_keys($categories) !== range(0, count($categories) - 1);

        if ($isAssoc) {
            $lines = [];
            $keys = [];
            foreach ($categories as $key => $label) {
                $keys[] = (string) $key;
                $lines[] = "- {$key}: {$label}";
            }
            $list = implode("\n", $lines);
            $allowed = implode(', ', $keys);

            return 'Classify the following text into exactly one of these categories. '
                ."Return only the category key (one of: {$allowed}), no explanations.\n\n"
                ."Categories:\n{$list}\n\n"
                ."Text:\n{$text}";
        }

        $list = implode(', ', $categories);

        return "Classify the following text into exactly one of: {$list}. "
            ."Return only the category name, no explanations.\n\n"
            ."Text:\n{$text}";
    }

    /**
     * Extrai dados estruturados como JSON, descrevendo cada campo do schema.
     *
     * @param array<string, string> $schema field => description
     */
    public static function extractJson(string $text, array $schema): string
    {
        $lines = [];
        foreach ($schema as $field => $description) {
            $lines[] = "- {$field}: {$description}";
        }
        $fields = implode("\n", $lines);

        return 'Extract the following fields from the text and return a single JSON object. '
            ."Return only valid JSON, no markdown fences, no prose.\n\n"
            ."Fields:\n{$fields}\n\n"
            ."Text:\n{$text}";
    }

    /**
     * Gera um slug URL-friendly a partir de um título.
     */
    public static function generateSlug(string $title): string
    {
        return 'Generate a URL-friendly slug for the following title. '
            .'Use only lowercase letters, numbers and hyphens. No leading/trailing hyphens. '
            .'Return only the slug, no explanations. '
            ."Example: 'Hello World!' -> 'hello-world'.\n\n"
            ."Title: {$title}";
    }

    /**
     * Extrai N keywords do texto.
     */
    public static function keywordExtract(string $text, int $count = 5): string
    {
        return "Extract the {$count} most relevant keywords from the following text. "
            ."Return them as a comma-separated list, no numbering, no explanations.\n\n"
            ."Text:\n{$text}";
    }

    /**
     * Reescreve `$text` no tom desejado (professional, casual, friendly, ...).
     */
    public static function tone(string $text, string $tone = 'professional'): string
    {
        return "Rewrite the following text in a {$tone} tone, preserving meaning and key facts. "
            ."Return only the rewritten text, no explanations.\n\n"
            ."Text:\n{$text}";
    }

    /**
     * Corrige gramática/ortografia preservando voz e conteúdo.
     */
    public static function proofread(string $text): string
    {
        return 'Proofread the following text. Correct grammar, spelling and punctuation only — '
            .'do not change meaning, tone or wording beyond what is necessary. '
            ."Return only the corrected text, no explanations.\n\n"
            ."Text:\n{$text}";
    }

    /**
     * Registra um prompt template customizado. Sobre-escreve silenciosamente
     * se o nome já existir.
     *
     * @param Closure(array<string,mixed>):string $template
     */
    public static function register(string $name, Closure $template): void
    {
        self::$custom[$name] = $template;
    }

    /**
     * Verifica se um prompt customizado existe.
     */
    public static function has(string $name): bool
    {
        return isset(self::$custom[$name]);
    }

    /**
     * Resolve um prompt customizado registrado, invocando o closure com `$data`.
     *
     * @param array<string,mixed> $data
     *
     * @throws InvalidArgumentException quando o nome não está registrado.
     */
    public static function resolve(string $name, array $data = []): string
    {
        if (! isset(self::$custom[$name])) {
            throw new InvalidArgumentException("Custom prompt [{$name}] is not registered.");
        }

        return (self::$custom[$name])($data);
    }

    /**
     * Esvazia o registro de prompts customizados (útil em testes).
     */
    public static function clear(): void
    {
        self::$custom = [];
    }
}
