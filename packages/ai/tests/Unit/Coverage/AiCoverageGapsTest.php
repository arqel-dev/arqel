<?php

declare(strict_types=1);

use Arqel\Ai\AiCache;
use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\AiManager;
use Arqel\Ai\CostTracker;
use Arqel\Ai\Exceptions\AiException;
use Arqel\Ai\Fields\AiExtractField;
use Arqel\Ai\Fields\AiSelectField;
use Arqel\Ai\Fields\AiTextField;
use Arqel\Ai\Fields\AiTranslateField;
use Arqel\Ai\Models\AiUsage;
use Arqel\Ai\Prompts\PromptLibrary;
use Arqel\Ai\Providers\ClaudeProvider;
use Arqel\Ai\Providers\OllamaProvider;
use Arqel\Ai\Providers\OpenAiProvider;
use Arqel\Ai\Tests\Fixtures\ConfigurableFakeProvider;
use Arqel\Ai\Tests\Fixtures\FakeProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    PromptLibrary::clear();
    config()->set('arqel-ai.caching.enabled', false);
});

/*
|--------------------------------------------------------------------------
| AiManager — gaps de resolução de provider e cache hit
|--------------------------------------------------------------------------
*/

it('throws when default provider is null and options has no provider', function (): void {
    config()->set('arqel-ai.default_provider', null);

    $manager = new AiManager(providers: ['fake' => new FakeProvider]);

    expect(fn () => $manager->complete('oi'))
        ->toThrow(InvalidArgumentException::class);
});

it('returns cached result without calling provider on cache hit', function (): void {
    config()->set('arqel-ai.caching.enabled', true);
    config()->set('arqel-ai.caching.ttl', 60);

    $provider = new FakeProvider;
    $cache = new AiCache;
    $cached = new AiCompletionResult('cached-text', 1, 2, 0.0001, 'cached-model', []);

    $cache->put('hello', [], $cached);

    $manager = new AiManager(
        providers: ['fake' => $provider],
        cache: $cache,
    );
    config()->set('arqel-ai.default_provider', 'fake');

    $result = $manager->complete('hello');

    expect($result->text)->toBe('cached-text')
        ->and($result->model)->toBe('cached-model')
        ->and($provider->completeCalls)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| AiCache — determinismo da key
|--------------------------------------------------------------------------
*/

it('AiCache::key() é determinística para mesmo prompt + options', function (): void {
    $cache = new AiCache;

    $a = $cache->key('texto', ['temperature' => 0.4, 'model' => 'claude-opus-4-7']);
    $b = $cache->key('texto', ['temperature' => 0.4, 'model' => 'claude-opus-4-7']);

    expect($a)->toBe($b)
        ->and($a)->toStartWith('arqel-ai:')
        // mudar prompt → key diferente
        ->and($cache->key('outro', ['temperature' => 0.4, 'model' => 'claude-opus-4-7']))->not->toBe($a)
        // mudar options → key diferente
        ->and($cache->key('texto', ['temperature' => 0.5, 'model' => 'claude-opus-4-7']))->not->toBe($a);
});

/*
|--------------------------------------------------------------------------
| CostTracker — null cost + filtro temporal
|--------------------------------------------------------------------------
*/

it('records uma linha com cost_usd null quando estimatedCost é null', function (): void {
    $result = new AiCompletionResult('hi', 1, 2, null, 'm', []);

    (new CostTracker)->record(11, $result, 'ollama');

    $row = AiUsage::query()->firstOrFail();

    expect($row->user_id)->toBe(11)
        ->and($row->cost_usd)->toBeNull()
        ->and($row->input_tokens)->toBe(1)
        ->and($row->output_tokens)->toBe(2);
});

it('getCostSince ignora linhas anteriores ao corte temporal', function (): void {
    Carbon::setTestNow('2026-04-30 12:00:00');

    // created_at não está em $fillable do AiUsage — força via update direto após
    // o insert para desviar do auto-stamp do Eloquent.
    $old = AiUsage::query()->create([
        'user_id' => null,
        'provider' => 'claude',
        'model' => 'claude-opus-4-7',
        'input_tokens' => 100,
        'output_tokens' => 100,
        'cost_usd' => 5.0,
    ]);
    $old->forceFill(['created_at' => Carbon::parse('2026-04-29 23:00:00')])->saveQuietly();

    $today = AiUsage::query()->create([
        'user_id' => null,
        'provider' => 'claude',
        'model' => 'claude-opus-4-7',
        'input_tokens' => 100,
        'output_tokens' => 100,
        'cost_usd' => 1.25,
    ]);
    $today->forceFill(['created_at' => Carbon::parse('2026-04-30 09:00:00')])->saveQuietly();

    $tracker = new CostTracker;

    // Carbon::today() em 2026-04-30 12:00 → 2026-04-30 00:00:00 → conta só a 2ª linha.
    expect($tracker->getCostSince())->toBe(1.25);

    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| ClaudeProvider — pricing zero
|--------------------------------------------------------------------------
*/

it('ClaudeProvider::estimateCost dá 0.0 quando ambos os contadores são zero', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-4-7',
            'content' => [['type' => 'text', 'text' => 'ok']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 0, 'output_tokens' => 0],
        ]),
    ]);

    $provider = new ClaudeProvider(apiKey: 'sk-test');
    $result = $provider->complete('ping');

    expect($result->estimatedCost)->toBe(0.0)
        ->and($result->inputTokens)->toBe(0)
        ->and($result->outputTokens)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| OpenAiProvider — system message ausente não vira system vazio
|--------------------------------------------------------------------------
*/

it('OpenAiProvider::chat NÃO injeta system role quando options.system não é informado', function (): void {
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response([
            'id' => 'cmpl-1',
            'object' => 'chat.completion',
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'oi'],
                'finish_reason' => 'stop',
                'index' => 0,
            ]],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
            'model' => 'gpt-4o-mini',
        ]),
    ]);

    $provider = new OpenAiProvider(apiKey: 'sk-test');
    $provider->chat(messages: [['role' => 'user', 'content' => 'hi']]);

    Http::assertSent(function (Request $request) {
        $messages = $request->data()['messages'] ?? [];
        if (! is_array($messages) || count($messages) !== 1) {
            return false;
        }
        $first = $messages[0] ?? null;

        return is_array($first) && ($first['role'] ?? null) === 'user';
    });
});

/*
|--------------------------------------------------------------------------
| OllamaProvider — eval_count ausente cai em 0
|--------------------------------------------------------------------------
*/

it('OllamaProvider::chat usa fallback 0 quando eval_count está ausente', function (): void {
    Http::fake([
        'localhost:11434/api/chat' => Http::response([
            'model' => 'llama3.1',
            'message' => ['role' => 'assistant', 'content' => 'pong'],
            // sem prompt_eval_count e sem eval_count
        ]),
    ]);

    $provider = new OllamaProvider;
    $result = $provider->chat([['role' => 'user', 'content' => 'ping']]);

    expect($result->text)->toBe('pong')
        ->and($result->inputTokens)->toBe(0)
        ->and($result->outputTokens)->toBe(0)
        ->and($result->estimatedCost)->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| AiTextField — propaga AiException do manager
|--------------------------------------------------------------------------
*/

it('AiTextField::generate propaga AiException quando manager falha', function (): void {
    config()->set('arqel-ai.default_provider', 'broken');

    $broken = new class extends FakeProvider
    {
        public function complete(string $prompt, array $options = []): AiCompletionResult
        {
            throw new AiException('provider down');
        }
    };

    app()->instance(AiManager::class, new AiManager(providers: ['broken' => $broken]));

    $field = (new AiTextField('summary'))
        ->prompt('Resume {title}.');

    expect(fn () => $field->generate(['title' => 'Hello']))
        ->toThrow(AiException::class, 'provider down');
});

/*
|--------------------------------------------------------------------------
| AiSelectField — output da AI cercado de prosa cai em fallback
|--------------------------------------------------------------------------
*/

it('AiSelectField::classify usa fallback quando AI devolve prosa em vez da key', function (): void {
    $provider = new ConfigurableFakeProvider;
    $provider->textToReturn = 'Based on the input, the answer is `tech`.';

    app()->instance(AiManager::class, new AiManager(providers: ['fake' => $provider]));
    config()->set('arqel-ai.default_provider', 'fake');

    $field = (new AiSelectField('category'))
        ->options(['tech' => 'Tech', 'finance' => 'Finance'])
        ->prompt('Classify {title}')
        ->fallbackOption('tech');

    // Normalize() faz trim/lower/strip de bordas — a frase inteira não vira
    // a key 'tech', mas o fallback declarativo cobre o caso.
    expect($field->classify(['title' => 'O que é Solidity']))->toBe('tech');
});

/*
|--------------------------------------------------------------------------
| AiTranslateField — todas as línguas preenchidas → no-op (sem chamadas AI)
|--------------------------------------------------------------------------
*/

it('AiTranslateField::translateAll é no-op quando todas as línguas estão preenchidas', function (): void {
    $provider = new ConfigurableFakeProvider;
    $provider->textToReturn = 'NEVER CALLED';

    app()->instance(AiManager::class, new AiManager(providers: ['fake' => $provider]));
    config()->set('arqel-ai.default_provider', 'fake');

    $field = (new AiTranslateField('description'))
        ->languages(['en', 'pt-BR', 'es'])
        ->defaultLanguage('en');

    $translations = [
        'en' => 'Hello',
        'pt-BR' => 'Olá',
        'es' => 'Hola',
    ];

    $result = $field->translateAll($translations, 'en');

    expect($result)->toBe($translations)
        ->and($provider->completeCalls)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| AiExtractField — keys extras devolvidas pela AI são filtradas
|--------------------------------------------------------------------------
*/

it('AiExtractField::extract filtra keys fora do schema declarado', function (): void {
    $provider = new ConfigurableFakeProvider;
    $provider->textToReturn = (string) json_encode([
        'total' => '99.90 BRL',
        'due_date' => '2026-05-30',
        'unwanted_key' => 'should be filtered',
        'another_extra' => 42,
    ]);

    app()->instance(AiManager::class, new AiManager(providers: ['fake' => $provider]));
    config()->set('arqel-ai.default_provider', 'fake');

    $field = (new AiExtractField('extracted'))
        ->sourceField('raw_text')
        ->extractTo([
            'total' => 'Total amount with currency',
            'due_date' => 'Due date ISO',
            'invoice_number' => 'Invoice ID — pode estar ausente',
        ]);

    $output = $field->extract('Total: R$ 99,90 vence em 2026-05-30');

    expect($output)->toHaveKeys(['total', 'due_date', 'invoice_number'])
        ->and($output)->not->toHaveKey('unwanted_key')
        ->and($output)->not->toHaveKey('another_extra')
        ->and($output['total'])->toBe('99.90 BRL')
        ->and($output['due_date'])->toBe('2026-05-30')
        // Key declarada mas ausente na resposta da AI → null (distingue de "key extra").
        ->and($output['invoice_number'])->toBeNull();
});

/*
|--------------------------------------------------------------------------
| PromptLibrary::extractJson — schema vazio
|--------------------------------------------------------------------------
*/

it('PromptLibrary::extractJson com schema vazio gera prompt sem fields list', function (): void {
    $prompt = PromptLibrary::extractJson('algum texto', []);

    // O bloco "Fields:" continua presente (literal), mas sem nenhuma linha
    // listando campos — útil para validar que o helper não quebra com schema vazio.
    expect($prompt)->toContain('Fields:')
        ->and($prompt)->toContain('Text:')
        ->and($prompt)->toContain('algum texto')
        ->and($prompt)->not->toContain('- :');
});
