<?php

declare(strict_types=1);

namespace Arqel\Import\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the failed-rows CSV produced by ProcessImportJob.
 *
 * Authorization: registered under `web + auth`; the `importId` route
 * constraint (`[a-f0-9-]+`) prevents path traversal. The failed-rows CSV
 * may contain PII from the imported data and is NOT ownership-bound — any
 * authenticated user who knows the (unguessable UUIDv4) importId can fetch
 * it. Consumer apps MUST wrap this route with their own authorization
 * (e.g. a `can:` middleware keyed to the import's owner), because the
 * package has no knowledge of your ownership model.
 *
 * @internal Esta classe é interna ao Arqel (ADR-019) e pode mudar em qualquer minor.
 */
final class FailedRowsDownloadController
{
    public function __invoke(string $importId): Response
    {
        $path = storage_path('app/arqel-imports/failed-'.$importId.'.csv');

        abort_unless(is_file($path), Response::HTTP_NOT_FOUND);

        return response()->download($path, 'failed-'.$importId.'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
