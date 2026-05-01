<?php

declare(strict_types=1);

namespace Arqel\Ai\Tests\Fixtures;

use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\Contracts\AiProvider;
use Arqel\Ai\Exceptions\AiException;

/**
 * Provider configurável para testes que precisam controlar o texto
 * retornado por `complete()`. Usado pelo `AiSelectFieldTest` para
 * validar parsing/normalização do output da AI (lowercase, trim,
 * strip de aspas/pontuação) e o caminho de fallback para keys
 * inválidas.
 */
class ConfigurableFakeProvider implements AiProvider
{
    public int $completeCalls = 0;

    public ?string $lastPrompt = null;

    public string $textToReturn = '';

    /** @var array<int, string> */
    public array $textsToReturn = [];

    /** @var array<int, string> */
    public array $promptHistory = [];

    /** @var array<int, array<string, mixed>> */
    public array $optionsHistory = [];

    public function __construct(
        private readonly string $name = 'fake',
        private readonly float $costPerCall = 0.001,
    ) {}

    public function complete(string $prompt, array $options = []): AiCompletionResult
    {
        $this->completeCalls++;
        $this->lastPrompt = $prompt;
        $this->promptHistory[] = $prompt;
        $this->optionsHistory[] = $options;

        $text = $this->textsToReturn !== []
            ? (string) array_shift($this->textsToReturn)
            : $this->textToReturn;

        return new AiCompletionResult($text, 5, 5, $this->costPerCall, 'fake-model', []);
    }

    public function chat(array $messages, array $options = []): AiCompletionResult
    {
        return new AiCompletionResult($this->textToReturn, 5, 5, $this->costPerCall, 'fake-model', []);
    }

    public function embed(string $text): array
    {
        return [0.1, 0.2, 0.3];
    }

    public function stream(string $prompt, callable $onChunk, array $options = []): void
    {
        throw new AiException('stream not implemented in ConfigurableFakeProvider');
    }

    public function name(): string
    {
        return $this->name;
    }

    public function supportsEmbeddings(): bool
    {
        return true;
    }

    public function supportsStreaming(): bool
    {
        return false;
    }
}
