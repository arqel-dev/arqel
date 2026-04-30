<?php

declare(strict_types=1);

namespace Arqel\Ai\Http\Controllers;

use Arqel\Ai\Fields\AiTranslateField;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Endpoint single-action que recebe `{resource, field, sourceLanguage,
 * targetLanguages, sourceText}` e devolve `['translations' => [...]]`
 * com cada idioma alvo traduzido por `AiTranslateField::translate()`.
 *
 * O `ResourceRegistry` é resolvido via FQCN string para evitar
 * hard-dep sobre `arqel/core` durante o tipagem (o package já
 * exige o pacote em `composer.json` mas o controller fica
 * tolerante a ambientes onde a classe não está bound).
 *
 * Authorization: Gate `use-ai` opt-in — quando ausente, allow por
 * default. Apps que precisam hard-gate AI registram a Gate em
 * `AppServiceProvider`.
 */
final class AiTranslateController
{
    private const RESOURCE_REGISTRY = 'Arqel\\Core\\Resources\\ResourceRegistry';

    public function __invoke(Request $request, string $resource, string $field): JsonResponse
    {
        if (Gate::has('use-ai') && ! Gate::allows('use-ai')) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        try {
            /** @var object $registry */
            $registry = app(self::RESOURCE_REGISTRY);
        } catch (BindingResolutionException $e) {
            return new JsonResponse(['message' => 'ResourceRegistry not bound: '.$e->getMessage()], 404);
        }

        if (! method_exists($registry, 'findBySlug')) {
            return new JsonResponse(['message' => 'ResourceRegistry contract mismatch'], 404);
        }

        /** @var class-string|null $resourceClass */
        $resourceClass = $registry->findBySlug($resource);

        if ($resourceClass === null || ! is_string($resourceClass) || ! class_exists($resourceClass)) {
            return new JsonResponse(['message' => "Resource [{$resource}] not registered"], 404);
        }

        try {
            /** @var object $instance */
            $instance = app($resourceClass);
            $fields = method_exists($instance, 'fields') ? $instance->fields() : [];
        } catch (Throwable $e) {
            return new JsonResponse(['message' => 'Failed to resolve resource fields: '.$e->getMessage()], 500);
        }

        $translateField = null;
        if (is_array($fields)) {
            foreach ($fields as $candidate) {
                if (! $candidate instanceof AiTranslateField) {
                    continue;
                }
                if ($candidate->getName() === $field) {
                    $translateField = $candidate;
                    break;
                }
            }
        }

        if ($translateField === null) {
            return new JsonResponse(['message' => "AiTranslateField [{$field}] not found on resource [{$resource}]"], 422);
        }

        $rawSourceLanguage = $request->input('sourceLanguage', '');
        $rawSourceText = $request->input('sourceText', '');
        $sourceLanguage = is_string($rawSourceLanguage) ? $rawSourceLanguage : '';
        $sourceText = is_string($rawSourceText) ? $rawSourceText : '';

        /** @var array<int, string> $targetLanguages */
        $targetLanguages = (array) $request->input('targetLanguages', []);

        $translations = [];
        foreach ($targetLanguages as $target) {
            if (! is_string($target) || $target === '') {
                continue;
            }
            $translations[$target] = $translateField->translate(
                $sourceText,
                $target,
                $sourceLanguage !== '' ? $sourceLanguage : null,
            );
        }

        return new JsonResponse(['translations' => $translations]);
    }
}
