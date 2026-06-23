<?php

declare(strict_types=1);

namespace Arqel\Ai\Http\Controllers;

use Arqel\Ai\Exceptions\AiException;
use Arqel\Ai\Exceptions\DailyLimitExceeded;
use Arqel\Ai\Exceptions\UserLimitExceeded;
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
            return new JsonResponse(['message' => __('arqel::messages.ai.forbidden')], 403);
        }

        $resourceClass = $this->registry->findBySlug($resource);

        if ($resourceClass === null) {
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
            return new JsonResponse(['message' => __('arqel::messages.ai.field_not_found', ['type' => 'AiTextField', 'field' => $field, 'resource' => $resource])], 422);
        }

        /** @var array<string, mixed> $formData */
        $formData = (array) $request->input('formData', []);

        try {
            $text = $aiField->generate($formData);
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

        return new JsonResponse(['text' => $text]);
    }
}
