<?php

declare(strict_types=1);

namespace Arqel\Import\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the failed-rows CSV produced by ProcessImportJob.
 *
 * Authorization: registered under `web + auth`; the `importId` route
 * constraint (`[a-f0-9-]+`) prevents path traversal. Consumer apps
 * SHOULD gate this with the same ability as the upload.
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
