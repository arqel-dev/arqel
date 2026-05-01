<?php

declare(strict_types=1);

namespace Arqel\Ai\Http\Controllers;

use Arqel\Ai\Exceptions\AiException;
use Arqel\Ai\Fields\AiExtractField;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Endpoint single-action que recebe `{resource, field, sourceText}` e
 * devolve `{extracted: array<string, mixed>}` com os dados estruturados
 * extraídos pelo `AiExtractField::extract()`.
 *
 * `ResourceRegistry` é resolvido via FQCN string para evitar
 * hard-dep sobre `arqel/core` durante o tipagem (consistente com
 * `AiTranslateController`/`AiClassifyController`).
 *
 * Authorization: Gate `use-ai` opt-in — quando ausente, allow por
 * default.
 */
final class AiExtractController
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

        $extractField = null;
        if (is_array($fields)) {
            foreach ($fields as $candidate) {
                if (! $candidate instanceof AiExtractField) {
                    continue;
                }
                if ($candidate->getName() === $field) {
                    $extractField = $candidate;
                    break;
                }
            }
        }

        if ($extractField === null) {
            return new JsonResponse(['message' => "AiExtractField [{$field}] not found on resource [{$resource}]"], 422);
        }

        $rawSourceText = $request->input('sourceText', '');
        $sourceText = is_string($rawSourceText) ? $rawSourceText : '';

        try {
            $extracted = $extractField->extract($sourceText);
        } catch (AiException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        return new JsonResponse(['extracted' => $extracted]);
    }
}
