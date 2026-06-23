<?php

declare(strict_types=1);

namespace Arqel\Ai\Http\Controllers;

use Arqel\Ai\Exceptions\AiException;
use Arqel\Ai\Exceptions\DailyLimitExceeded;
use Arqel\Ai\Exceptions\UserLimitExceeded;
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
 * hard-dep sobre `arqel-dev/core` durante o tipagem (consistente com
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
            return new JsonResponse(['message' => __('arqel::messages.ai.forbidden')], 403);
        }

        try {
            /** @var object $registry */
            $registry = app(self::RESOURCE_REGISTRY);
        } catch (BindingResolutionException $e) {
            report($e);

            return new JsonResponse(['message' => __('arqel::messages.ai.registry_unbound')], 404);
        }

        if (! method_exists($registry, 'findBySlug')) {
            return new JsonResponse(['message' => __('arqel::messages.ai.registry_contract_mismatch')], 404);
        }

        /** @var class-string|null $resourceClass */
        $resourceClass = $registry->findBySlug($resource);

        if ($resourceClass === null || ! is_string($resourceClass) || ! class_exists($resourceClass)) {
            return new JsonResponse(['message' => __('arqel::messages.ai.resource_not_registered', ['resource' => $resource])], 404);
        }

        try {
            /** @var object $instance */
            $instance = app($resourceClass);
            $fields = method_exists($instance, 'effectiveFields')
                ? $instance->effectiveFields()
                : (method_exists($instance, 'fields') ? $instance->fields() : []);
        } catch (Throwable $e) {
            report($e);

            return new JsonResponse(['message' => __('arqel::messages.ai.field_resolution_failed')], 500);
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
            return new JsonResponse(['message' => __('arqel::messages.ai.field_not_found', ['type' => 'AiExtractField', 'field' => $field, 'resource' => $resource])], 422);
        }

        $rawSourceText = $request->input('sourceText', '');
        $sourceText = is_string($rawSourceText) ? $rawSourceText : '';

        try {
            $extracted = $extractField->extract($sourceText);
        } catch (DailyLimitExceeded|UserLimitExceeded $e) {
            // Framework-controlled, user-facing limit messages — safe to show.
            return new JsonResponse(['message' => $e->getMessage()], 422);
        } catch (AiException $e) {
            // A bare AiException may embed the raw upstream provider response
            // body (see OpenAiProvider/OllamaProvider). Never reflect it to the
            // client; log it server-side and return a fixed generic message.
            report($e);

            return new JsonResponse(['message' => __('arqel::messages.ai.provider_failed')], 422);
        }

        return new JsonResponse(['extracted' => $extracted]);
    }
}
