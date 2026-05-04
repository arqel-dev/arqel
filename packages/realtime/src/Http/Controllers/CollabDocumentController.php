<?php

declare(strict_types=1);

namespace Arqel\Realtime\Http\Controllers;

use Arqel\Realtime\Collab\YjsDocument;
use Arqel\Realtime\Events\YjsUpdateReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller REST para snapshot persistence de Y.Docs (RT-005 scaffold).
 *
 * - `GET  /admin/{resource}/{id}/collab/{field}` — devolve `{state, version}`
 *   onde `state` é base64 do blob binário Yjs ou `null` se ainda não existe.
 * - `POST /admin/{resource}/{id}/collab/{field}` — recebe `{state, version}`
 *   (state base64) e aplica optimistic concurrency: aceita quando
 *   `incoming.version >= current.version`, devolve `409` caso contrário.
 *
 * Sync delta-by-delta de Yjs (via WebSocket / Reverb) fica para
 * follow-up (`@arqel-dev/realtime` cliente). Aqui só persistimos snapshot
 * consolidado.
 */
final class CollabDocumentController
{
    public function show(Request $request, string $resource, string $id, string $field): JsonResponse
    {
        $document = YjsDocument::query()
            ->where('model_type', $resource)
            ->where('model_id', $id)
            ->where('field', $field)
            ->first();

        if ($document === null) {
            return new JsonResponse([
                'state' => null,
                'version' => 0,
            ]);
        }

        $stateBlob = $document->state;
        $stateBase64 = $stateBlob === null || $stateBlob === ''
            ? null
            : base64_encode($stateBlob);

        return new JsonResponse([
            'state' => $stateBase64,
            'version' => $document->version,
        ]);
    }

    public function store(Request $request, string $resource, string $id, string $field): JsonResponse
    {
        $rawState = $request->input('state');
        $rawVersion = $request->input('version', 0);

        if (! is_string($rawState) || $rawState === '') {
            return new JsonResponse(['message' => 'state must be a non-empty base64 string'], 422);
        }

        $incomingVersion = is_numeric($rawVersion) ? (int) $rawVersion : 0;

        $stateBlob = base64_decode($rawState, true);
        if ($stateBlob === false) {
            return new JsonResponse(['message' => 'state is not valid base64'], 422);
        }

        $document = YjsDocument::query()
            ->where('model_type', $resource)
            ->where('model_id', $id)
            ->where('field', $field)
            ->first();

        if ($document === null) {
            $userId = $this->resolveUserId($request);
            $document = new YjsDocument;
            $document->model_type = $resource;
            $document->model_id = (int) $id;
            $document->field = $field;
            $document->state = $stateBlob;
            $document->version = max($incomingVersion, 1);
            $document->last_user_id = $userId;
            $document->updated_at = now();
            $document->save();

            YjsUpdateReceived::dispatch(
                $resource,
                (int) $id,
                $field,
                $rawState,
                $document->version,
                $document->last_user_id,
            );

            return new JsonResponse([
                'version' => $document->version,
            ], 201);
        }

        if ($incomingVersion < $document->version) {
            return new JsonResponse([
                'message' => 'version conflict',
                'serverVersion' => $document->version,
            ], 409);
        }

        $document->state = $stateBlob;
        $document->version = $document->version + 1;
        $document->last_user_id = $this->resolveUserId($request);
        $document->updated_at = now();
        $document->save();

        YjsUpdateReceived::dispatch(
            $resource,
            (int) $id,
            $field,
            $rawState,
            $document->version,
            $document->last_user_id,
        );

        return new JsonResponse([
            'version' => $document->version,
        ]);
    }

    private function resolveUserId(Request $request): ?int
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $id = $user->getAuthIdentifier();

        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }
}
