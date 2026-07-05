<?php

declare(strict_types=1);

namespace Arqel\Import\Http\Controllers;

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
 */
final class ImportUploadController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
            'importer' => ['required', 'string'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');
        $format = ImportFormat::fromExtension($file->getClientOriginalExtension());

        $importId = (string) Str::uuid();
        $stored = $file->storeAs('arqel-imports', $importId.'.'.$format->extension());
        $sourcePath = storage_path('app/'.$stored);

        ProcessImportJob::dispatch($importId, $format, $validated['importer'], $sourcePath);

        return back()->with('success', 'arqel::import.queued');
    }
}
