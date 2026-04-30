<?php

declare(strict_types=1);

namespace Arqel\Export\Http\Controllers;

use Arqel\Export\ExportFormat;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a previously generated export file by UUID.
 *
 * Lookup strategy: globs `<destinationDir>/export-<exportId>.*` and
 * expects exactly one match. Zero matches → 404, multiple matches →
 * 404 (ambiguous, treated as not-found rather than guessing).
 *
 * SECURITY NOTE: this controller does NOT enforce ownership or any
 * authorization. Consumer apps MUST wrap the route with their own
 * middleware — typically:
 *
 * ```php
 * Route::middleware(['auth', 'can:download-exports'])
 *     ->get('/admin/exports/{exportId}/download', ...);
 * ```
 *
 * The bundled `routes/admin.php` only adds `web` + `auth`; tightening
 * the policy is the consumer's responsibility because the package
 * does not know about the User model nor the Export ownership column.
 */
final class ExportDownloadController
{
    /**
     * @var non-empty-string
     */
    private const UUID_PATTERN = '/^[a-f0-9-]+$/';

    public function download(string $exportId, Request $request): BinaryFileResponse
    {
        if (preg_match(self::UUID_PATTERN, $exportId) !== 1) {
            abort(400, 'Invalid export id.');
        }

        $dir = $this->resolveDirectory();
        $matches = glob(rtrim($dir, '/').'/export-'.$exportId.'.*');

        if ($matches === false || count($matches) === 0) {
            abort(404, 'Export not found.');
        }

        if (count($matches) > 1) {
            abort(404, 'Export ambiguous.');
        }

        $filePath = $matches[0];
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $format = ExportFormat::tryFrom(strtolower($extension));

        $headers = [];
        if ($format !== null) {
            $headers['Content-Type'] = $format->mimeType();
        }

        return response()->download($filePath, basename($filePath), $headers);
    }

    private function resolveDirectory(): string
    {
        /** @var mixed $configured */
        $configured = config('arqel-export.destination_dir');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return storage_path('app/arqel-exports');
    }
}
