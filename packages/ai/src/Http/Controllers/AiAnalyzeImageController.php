<?php

declare(strict_types=1);

namespace Arqel\Ai\Http\Controllers;

use Arqel\Ai\Exceptions\AiException;
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
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        $rawImageUrl = $request->input('imageUrl', '');
        $rawImageBase64 = $request->input('imageBase64', '');
        $imageUrl = is_string($rawImageUrl) ? $rawImageUrl : '';
        $imageBase64 = is_string($rawImageBase64) ? $rawImageBase64 : '';

        if ($imageUrl === '' && $imageBase64 === '') {
            return new JsonResponse(
                ['message' => 'Either imageUrl or imageBase64 must be provided'],
                422,
            );
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
            return new JsonResponse(['message' => "AiImageField [{$field}] not found on resource [{$resource}]"], 422);
        }

        try {
            $analyses = $imageField->analyze($imageUrl !== '' ? $imageUrl : $imageBase64);
        } catch (AiException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        return new JsonResponse([
            'analyses' => $analyses,
            'populateMapping' => $imageField->getPopulateFields(),
        ]);
    }
}
