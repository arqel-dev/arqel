<?php

declare(strict_types=1);

namespace Arqel\Import\Http\Controllers;

use Arqel\Import\ImportFormat;
use Arqel\Import\Jobs\ProcessImportJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Receives an import file upload, authorizes it, validates it, stores it
 * and dispatches the queued ProcessImportJob.
 *
 * Authorization: this route is registered under `web + auth`, so the
 * request always carries an authenticated user. In addition, the
 * controller consults the `import` Gate ability (`Gate::forUser($user)
 * ->denies('import')`) when a consumer app has registered one — either
 * via `Gate::define('import', ...)` or a Policy. When no such ability is
 * registered, the check silently allows ("scaffold mode"), mirroring
 * `Arqel\Fields\Http\Controllers\FieldUploadController::authorize()` so
 * a fresh install keeps working before the app wires its own Policies
 * (ADR-017: Policies/Gates are the canonical app-level mechanism).
 */
final class ImportUploadController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $this->authorize($request);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx'],
            'importer' => ['required', 'string'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');
        $format = ImportFormat::fromExtension($file->getClientOriginalExtension());

        $importId = (string) Str::uuid();
        $dir = storage_path('app/arqel-imports');
        if (! is_dir($dir) && ! @mkdir($dir, 0o755, true) && ! is_dir($dir)) {
            throw new RuntimeException(sprintf('Unable to create import upload directory [%s].', $dir));
        }

        $file->move($dir, $importId.'.'.$format->extension());
        $sourcePath = $dir.'/'.$importId.'.'.$format->extension();

        ProcessImportJob::dispatch($importId, $format, $validated['importer'], $sourcePath);

        return back()->with('success', __('arqel-import::import.queued'));
    }

    /**
     * Gate the upload against the `import` ability. Scaffold mode (no
     * Gate/Policy registered) silently allows, exactly like the sibling
     * `FieldUploadController` and `ResourceController::authorize()`.
     */
    private function authorize(Request $request): void
    {
        if (! Gate::has('import')) {
            return;
        }

        if (Gate::forUser($request->user())->denies('import')) {
            abort(HttpResponse::HTTP_FORBIDDEN);
        }
    }
}
