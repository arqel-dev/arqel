<?php

declare(strict_types=1);

namespace Arqel\Tenant\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Thrown by `ResolveTenantMiddleware` when no tenant could be
 * resolved for a request that requires one.
 *
 * Renders to:
 *   - JSON 404 when the request expects JSON (API/XHR)
 *   - Inertia view `arqel::errors.tenant-not-found` when the host
 *     app published it
 *   - Plain Symfony 404 response otherwise
 *
 * Apps can register a custom render via Laravel's exception
 * handler — this default keeps things working without any setup.
 */
class TenantNotFoundException extends Exception
{
    public function __construct(
        string $message = 'No tenant could be resolved for the request.',
        public readonly ?string $identifier = null,
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): Response
    {
        $payload = [
            'message' => $this->getMessage(),
            'tenantIdentifier' => $this->identifier ?? $request->getHost(),
        ];

        if ($request->expectsJson()) {
            return new JsonResponse($payload, Response::HTTP_NOT_FOUND);
        }

        if (function_exists('inertia') && $this->inertiaViewExists()) {
            $response = inertia('arqel::errors.tenant-not-found', $payload)
                ->toResponse($request);
            $response->setStatusCode(Response::HTTP_NOT_FOUND);

            return $response;
        }

        return new Response(
            content: $this->getMessage(),
            status: Response::HTTP_NOT_FOUND,
        );
    }

    private function inertiaViewExists(): bool
    {
        if (! function_exists('view')) {
            return false;
        }

        try {
            return view()->exists('arqel::errors.tenant-not-found');
        } catch (Throwable) {
            return false;
        }
    }
}
