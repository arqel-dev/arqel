<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Http\Controllers;

use Arqel\Marketplace\Events\PluginSubmitted;
use Arqel\Marketplace\Http\Requests\SubmitPluginRequest;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Services\PluginAutoChecker;
use Illuminate\Http\JsonResponse;

/**
 * Submissão pública de plugin (MKTPLC-002).
 *
 * Cria Plugin com `status=pending`, popula metadados de submissão e roda
 * o `PluginAutoChecker` para anexar relatório de checks à coluna
 * `submission_metadata`. Dispara `PluginSubmitted` ao final.
 */
final class PluginSubmissionController
{
    public function __invoke(SubmitPluginRequest $request, PluginAutoChecker $checker): JsonResponse
    {
        $user = $request->user();
        $userId = null;

        if ($user !== null) {
            $key = $user->getAuthIdentifier();
            $userId = is_numeric($key) ? (int) $key : null;
        }

        /** @var array{slug: string, composer_package: string, npm_package?: ?string, github_url: string, type: string, name: string, description: string, screenshots?: ?array<int, string>, license?: ?string} $data */
        $data = $request->validated();

        /** @var Plugin $plugin */
        $plugin = Plugin::query()->create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'],
            'type' => $data['type'],
            'composer_package' => $data['composer_package'],
            'npm_package' => $data['npm_package'] ?? null,
            'github_url' => $data['github_url'],
            'license' => $data['license'] ?? 'MIT',
            'screenshots' => $data['screenshots'] ?? null,
            'status' => 'pending',
            'submitted_by_user_id' => $userId,
            'submitted_at' => now(),
        ]);

        $report = $checker->check($plugin);

        $plugin->forceFill([
            'submission_metadata' => $report,
        ])->save();

        PluginSubmitted::dispatch($plugin);

        return new JsonResponse([
            'plugin' => [
                'id' => $plugin->id,
                'slug' => $plugin->slug,
                'name' => $plugin->name,
                'status' => $plugin->status,
                'submitted_by_user_id' => $plugin->submitted_by_user_id,
                'submitted_at' => $plugin->submitted_at?->toIso8601String(),
            ],
            'checks' => $report,
        ], 201);
    }
}
