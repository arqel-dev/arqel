<?php

declare(strict_types=1);

namespace Arqel\Core\Pulse;

use Arqel\Core\Pulse\Cards\ArqelAiTokensCard;
use Arqel\Core\Pulse\Cards\ArqelJobMetricsCard;
use Arqel\Core\Pulse\Cards\ArqelResourcesCard;
use Arqel\Core\Pulse\Cards\ArqelSlowQueriesCard;
use Arqel\Core\Pulse\Cards\ArqelTopActionsCard;
use Arqel\Core\Pulse\Recorders\ArqelActionRecorder;
use Arqel\Core\Pulse\Recorders\ArqelAiUsageRecorder;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Integração com Laravel Pulse (LCLOUD-003).
 *
 * Expõe cards e recorders Arqel-específicos no dashboard `/pulse` quando
 * `laravel/pulse` está instalado na app. Quando o pacote não está
 * presente, todos os métodos viram no-op silencioso — `arqel/core`
 * continua a funcionar sem hard-dep.
 *
 * Defensive em duas camadas:
 *   1. `isAvailable()` faz `class_exists` no Pulse.
 *   2. `register()` envolve cada side-effect em try/catch para nunca
 *      bloquear o boot da app.
 */
final readonly class PulseIntegration
{
    /**
     * Lista canónica de cards Arqel registados no dashboard Pulse.
     *
     * Tuplas `[component-tag, class]`. O Livewire registra cards via
     * `Livewire::component($tag, $class)` para que possam ser usados
     * no Blade view do dashboard como `<livewire:arqel-resources-card />`.
     *
     * @var list<array{0: string, 1: class-string}>
     */
    public const array CARDS = [
        ['arqel-resources-card', ArqelResourcesCard::class],
        ['arqel-top-actions-card', ArqelTopActionsCard::class],
        ['arqel-ai-tokens-card', ArqelAiTokensCard::class],
        ['arqel-job-metrics-card', ArqelJobMetricsCard::class],
        ['arqel-slow-queries-card', ArqelSlowQueriesCard::class],
    ];

    /**
     * Lista canónica de recorders Arqel.
     *
     * Cada recorder escuta um evento e grava métricas via Pulse. Os
     * eventos usados (Arqel\Actions\Events\ActionExecuted,
     * Arqel\Ai\Events\AiCompletionGenerated) podem não existir — os
     * recorders skip silenciosamente nesse caso.
     *
     * @var list<class-string>
     */
    public const array RECORDERS = [
        ArqelActionRecorder::class,
        ArqelAiUsageRecorder::class,
    ];

    public function __construct() {}

    /**
     * `true` se Laravel Pulse está instalado na app.
     */
    public function isAvailable(): bool
    {
        return class_exists(\Laravel\Pulse\Pulse::class);
    }

    /**
     * Regista cards Livewire e listeners de recorders.
     *
     * No-op se Pulse não está disponível. Cada falha é logada em
     * warning mas nunca propaga — boot do app é sagrado.
     */
    public function register(Application $app): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $this->registerCards();
        $this->registerRecorders();
    }

    /**
     * Tags Livewire dos cards efectivamente registados (debug helper).
     *
     * @return list<string>
     */
    public function registeredCardTags(): array
    {
        return array_map(
            static fn (array $tuple): string => $tuple[0],
            self::CARDS,
        );
    }

    /**
     * Versão do Laravel Pulse instalada, ou `null` se ausente.
     */
    public function pulseVersion(): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        try {
            if (class_exists(\Composer\InstalledVersions::class)
                && \Composer\InstalledVersions::isInstalled('laravel/pulse')) {
                return \Composer\InstalledVersions::getVersion('laravel/pulse');
            }
        } catch (Throwable) {
            // ignore
        }

        return 'unknown';
    }

    private function registerCards(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            return;
        }

        foreach (self::CARDS as [$tag, $class]) {
            try {
                \Livewire\Livewire::component($tag, $class);
            } catch (Throwable $e) {
                Log::warning('Arqel Pulse: failed to register card', [
                    'tag' => $tag,
                    'class' => $class,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function registerRecorders(): void
    {
        foreach (self::RECORDERS as $recorder) {
            try {
                if (! class_exists($recorder)) {
                    continue;
                }
                Event::subscribe($recorder);
            } catch (Throwable $e) {
                Log::warning('Arqel Pulse: failed to register recorder', [
                    'recorder' => $recorder,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
