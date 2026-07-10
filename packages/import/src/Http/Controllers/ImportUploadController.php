<?php

declare(strict_types=1);

namespace Arqel\Import\Http\Controllers;

use Arqel\Import\Importer;
use Arqel\Import\ImportFormat;
use Arqel\Import\Jobs\ProcessImportJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Receives an import file upload, validates it, stores it and dispatches
 * the queued ProcessImportJob.
 *
 * Authorization: this route is registered under `web + auth`. Consumer
 * apps SHOULD additionally gate it (e.g. the Resource `import` ability).
 *
 * @internal Esta classe é interna ao Arqel (ADR-019) e pode mudar em qualquer minor.
 */
final class ImportUploadController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Only csv/xlsx — the two formats ImportFormat knows. `txt` was
            // accepted here before but ImportFormat::fromExtension('txt')
            // throws, turning a benign upload into an unhandled HTTP 500.
            'file' => ['required', 'file', 'mimes:csv,xlsx'],
            'importer' => ['required', 'string'],
        ]);

        // `is_subclass_of(..., $allow_string = true)` checks the class string
        // without autoloading/instantiating it through the container, unlike
        // the job's `app($this->importerClass) instanceof Importer` guard.
        // Reject here, at the edge, before an arbitrary attacker-controlled
        // class string is ever passed further downstream.
        abort_unless(
            is_subclass_of($validated['importer'], Importer::class, true),
            422,
        );

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');
        $format = ImportFormat::fromExtension($file->getClientOriginalExtension());

        $importId = (string) Str::uuid();

        // Move the upload straight into `storage/app/arqel-imports` and hand
        // the job that exact absolute path. Using `storeAs()` + `storage_path`
        // desynced on Laravel 11/12, where the `local` disk root moved to
        // `storage/app/private`: the file landed there while the job received
        // `storage/app/arqel-imports`, a path that did not exist. Moving
        // directly (mirroring ProcessExportJob's destination) keeps the write
        // path, the job's `sourcePath`, and the failed-rows dir all in sync.
        $dir = storage_path('app/arqel-imports');
        $file->move($dir, $importId.'.'.$format->extension());
        $sourcePath = $dir.DIRECTORY_SEPARATOR.$importId.'.'.$format->extension();

        ProcessImportJob::dispatch($importId, $format, $validated['importer'], $sourcePath);

        return back()->with('success', (string) __('arqel-import::import.queued'));
    }
}
