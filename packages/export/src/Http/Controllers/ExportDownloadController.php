<?php

declare(strict_types=1);

namespace Arqel\Export\Http\Controllers;

use Arqel\Export\ExportFormat;
use Arqel\Export\Models\Export;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a previously generated export file to its owner.
 *
 * Resolves the `Export` record by id, then enforces ownership: the file
 * is served only when the record exists, has a non-null owner, and that
 * owner matches the authenticated user. Any ownership failure — missing
 * record, ownerless (legacy/CLI) export, or a different user — returns
 * 404 (not 403) so the existence of another user's export is never
 * confirmed (fail-closed, anti-enumeration).
 *
 * The bundled `routes/admin.php` gates the route with `web + auth`;
 * ownership is enforced here, in the package, so consumer apps no longer
 * need a bespoke policy to prevent cross-user downloads.
 *
 * @internal Esta classe é interna ao Arqel (ADR-019) e pode mudar em qualquer minor.
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
            abort(400, $this->message('arqel::messages.export.invalid_id', 'Invalid export id.'));
        }

        $export = Export::find($exportId);
        if ($export === null) {
            abort(404, $this->message('arqel::messages.export.not_found', 'Export not found.'));
        }

        $userId = $request->user()?->getAuthIdentifier();
        if ($export->owner_user_id === null
            || $userId === null
            || (string) $export->owner_user_id !== (string) $userId) {
            // 404 (not 403) so we never confirm the existence of another
            // user's export — fail-closed, anti-enumeration.
            abort(404, $this->message('arqel::messages.export.not_found', 'Export not found.'));
        }

        $filePath = $export->path;
        if (! is_string($filePath) || ! is_file($filePath)) {
            abort(404, $this->message('arqel::messages.export.not_found', 'Export not found.'));
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $format = ExportFormat::tryFrom(strtolower($extension));

        $headers = [];
        if ($format !== null) {
            $headers['Content-Type'] = $format->mimeType();
        }

        return response()->download($filePath, basename($filePath), $headers);
    }

    /**
     * Localize an abort message lazily so the request locale applies. Falls
     * back to the English literal when no translator is bound or the key is
     * untranslated, keeping the user-facing error text stable.
     */
    private function message(string $key, string $fallback): string
    {
        if (! app()->bound('translator')) {
            return $fallback;
        }

        $translated = trans($key);

        return is_string($translated) && $translated !== $key ? $translated : $fallback;
    }
}
