<?php

declare(strict_types=1);

namespace Arqel\Ai\Http\Controllers;

use Arqel\Ai\Exceptions\AiException;
use Arqel\Ai\Exceptions\DailyLimitExceeded;
use Arqel\Ai\Exceptions\UserLimitExceeded;
use Arqel\Ai\Fields\AiSelectField;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Endpoint single-action que recebe `{resource, field, formData}` e
 * devolve `{key, label}` onde `key` é a opção escolhida pelo
 * `AiSelectField::classify()` (ou `null` quando inválido sem fallback).
 *
 * Authorization: Gate `use-ai` opt-in — quando ausente, allow por
 * default.
 */
final class AiClassifyController
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

        $selectField = null;
        if (is_array($fields)) {
            foreach ($fields as $candidate) {
                if (! $candidate instanceof AiSelectField) {
                    continue;
                }
                if ($candidate->getName() === $field) {
                    $selectField = $candidate;
                    break;
                }
            }
        }

        if ($selectField === null) {
            return new JsonResponse(['message' => __('arqel::messages.ai.field_not_found', ['type' => 'AiSelectField', 'field' => $field, 'resource' => $resource])], 422);
        }

        /** @var array<string, mixed> $formData */
        $formData = (array) $request->input('formData', []);

        try {
            $key = $selectField->classify($formData);
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

        $options = $selectField->getOptions();
        $label = $key !== null ? ($options[$key] ?? null) : null;

        return new JsonResponse(['key' => $key, 'label' => $label]);
    }
}
