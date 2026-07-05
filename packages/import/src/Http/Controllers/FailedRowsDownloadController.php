<?php

declare(strict_types=1);

namespace Arqel\Import\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the failed-rows CSV produced by ProcessImportJob.
 *
 * Authorization: registered under `web + auth`; the `importId` route
 * constraint (`[a-f0-9-]+`) prevents path traversal. The controller also
 * consults the `import` Gate ability (same one as the upload endpoint)
 * when a consumer app has registered one, so a denied user gets a 403
 * instead of a silent download.
 *
 * KNOWN LIMITATION: this package does not persist an `imports` table nor
 * track which user created a given import (see `ImportLogger` — apps are
 * expected to bind their own implementation to record that). Because of
 * that, the built-in check here cannot compare "does this importId belong
 * to the requesting user" — it can only gate on the ability itself, same
 * as the sibling `Arqel\Export\Http\Controllers\ExportDownloadController`.
 * Consumer apps that need strict per-user ownership MUST additionally
 * bind an `ImportLogger` that records `userId => importId` and enforce
 * that mapping in their own middleware/Policy — e.g.:
 *
 * ```php
 * Gate::define('import', function ($user) {
 *     return $user->can('manage-imports');
 * });
 * ```
 *
 * or a route-level `can:` middleware that inspects a real ownership
 * store. Do not treat the bundled ability check alone as sufficient
 * tenant/user isolation.
 */
final class FailedRowsDownloadController
{
    public function __invoke(string $importId, Request $request): Response
    {
        $this->authorize($request);

        $path = storage_path('app/arqel-imports/failed-'.$importId.'.csv');

        abort_unless(is_file($path), Response::HTTP_NOT_FOUND);

        return response()->download($path, 'failed-'.$importId.'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Gate the download against the `import` ability. Scaffold mode (no
     * Gate/Policy registered) silently allows, mirroring the upload
     * controller and `FieldUploadController`.
     */
    private function authorize(Request $request): void
    {
        if (! Gate::has('import')) {
            return;
        }

        if (Gate::forUser($request->user())->denies('import')) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }
}
