<?php

declare(strict_types=1);

namespace Arqel\Ai\Http\Controllers;

use Arqel\Ai\Exceptions\AiException;
use Arqel\Ai\Exceptions\DailyLimitExceeded;
use Arqel\Ai\Exceptions\UserLimitExceeded;
use Arqel\Ai\Fields\AiImageField;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Endpoint single-action que recebe `{resource, field, imageUrl?,
 * imageBase64?}` e devolve `{analyses: <key=>result>, populateMapping:
 * <key=>target_form_field>}` rodando cada análise via
 * `AiImageField::analyze()`.
 *
 * Pelo menos um entre `imageUrl` ou `imageBase64` é obrigatório (422
 * quando ambos vazios). `ResourceRegistry` é resolvido via FQCN
 * string (consistente com `AiTranslateController`,
 * `AiClassifyController`, `AiExtractController`).
 *
 * Authorization: Gate `use-ai` opt-in — quando ausente, allow por
 * default.
 */
final class AiAnalyzeImageController
{
    private const RESOURCE_REGISTRY = 'Arqel\\Core\\Resources\\ResourceRegistry';

    public function __invoke(Request $request, string $resource, string $field): JsonResponse
    {
        if (Gate::has('use-ai') && ! Gate::allows('use-ai')) {
            return new JsonResponse(['message' => __('arqel::messages.ai.forbidden')], 403);
        }

        $rawImageUrl = $request->input('imageUrl', '');
        $rawImageBase64 = $request->input('imageBase64', '');
        $imageUrl = is_string($rawImageUrl) ? $rawImageUrl : '';
        $imageBase64 = is_string($rawImageBase64) ? $rawImageBase64 : '';

        if ($imageUrl === '' && $imageBase64 === '') {
            return new JsonResponse(
                ['message' => __('arqel::messages.ai.image_source_required')],
                422,
            );
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

        $imageField = null;
        if (is_array($fields)) {
            foreach ($fields as $candidate) {
                if (! $candidate instanceof AiImageField) {
                    continue;
                }
                if ($candidate->getName() === $field) {
                    $imageField = $candidate;
                    break;
                }
            }
        }

        if ($imageField === null) {
            return new JsonResponse(['message' => __('arqel::messages.ai.field_not_found', ['type' => 'AiImageField', 'field' => $field, 'resource' => $resource])], 422);
        }

        try {
            $analyses = $imageField->analyze($imageUrl !== '' ? $imageUrl : $imageBase64);
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

        return new JsonResponse([
            'analyses' => $analyses,
            'populateMapping' => $imageField->getPopulateFields(),
        ]);
    }
}
