<?php

declare(strict_types=1);

namespace Arqel\Ai\Http\Controllers;

use Arqel\Ai\Fields\AiTextField;
use Arqel\Core\Resources\ResourceRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Endpoint single-action que recebe `{resource, field, formData}` do
 * cliente, resolve o `AiTextField` correspondente via `ResourceRegistry`
 * e devolve o texto gerado por `AiManager`.
 *
 * Authorization: a Gate `use-ai` é consultada quando definida pela
 * aplicação. Quando ausente (`Gate::has('use-ai') === false`), o
 * acesso é permitido por default — apps que precisem hard-gate AI
 * devem registrar a Gate em `AppServiceProvider`.
 */
final class AiGenerateController
{
    public function __construct(
        private readonly ResourceRegistry $registry,
    ) {}

    public function __invoke(Request $request, string $resource, string $field): JsonResponse
    {
        if (Gate::has('use-ai') && ! Gate::allows('use-ai')) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        $resourceClass = $this->registry->findBySlug($resource);

        if ($resourceClass === null) {
            return new JsonResponse(['message' => "Resource [{$resource}] not registered"], 404);
        }

        try {
            /** @var object $instance */
            $instance = app($resourceClass);
            $fields = method_exists($instance, 'fields') ? $instance->fields() : [];
        } catch (Throwable $e) {
            return new JsonResponse(['message' => 'Failed to resolve resource fields: '.$e->getMessage()], 500);
        }

        $aiField = null;
        if (is_array($fields)) {
            foreach ($fields as $candidate) {
                if (! $candidate instanceof AiTextField) {
                    continue;
                }
                if ($candidate->getName() === $field) {
                    $aiField = $candidate;
                    break;
                }
            }
        }

        if ($aiField === null) {
            return new JsonResponse(['message' => "AiTextField [{$field}] not found on resource [{$resource}]"], 422);
        }

        /** @var array<string, mixed> $formData */
        $formData = (array) $request->input('formData', []);

        $text = $aiField->generate($formData);

        return new JsonResponse(['text' => $text]);
    }
}
