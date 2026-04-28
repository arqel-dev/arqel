<?php

declare(strict_types=1);

namespace Arqel\Fields\Http\Controllers;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Fields\Field;
use Arqel\Fields\Types\FileField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * Endpoints for the FileField/ImageField direct-upload pipeline.
 *
 * Routes:
 *   POST   {panel}/{resource}/fields/{field}/upload
 *   DELETE {panel}/{resource}/fields/{field}/upload
 *
 * Validation honours the field's configured `disk`, `directory`,
 * `maxSize` (in KB), and `acceptedFileTypes`. The response shape
 * mirrors the planning spec: `{path, url, size, originalName}`.
 *
 * The DELETE counterpart accepts a `path` form field and removes
 * it from the configured disk. Both endpoints expect the user to
 * be authenticated through the panel middleware.
 */
final class FieldUploadController
{
    public function __construct(
        private readonly ResourceRegistry $registry,
    ) {}

    public function store(Request $request, string $resource, string $field): JsonResponse
    {
        $instance = $this->resolveResourceOrFail($resource);
        $fieldInstance = $this->resolveFieldOrFail($instance, $field);

        if (! $fieldInstance instanceof FileField) {
            abort(HttpResponse::HTTP_BAD_REQUEST, 'Field is not a file upload.');
        }

        $rules = ['file' => $this->buildFileRules($fieldInstance)];
        $request->validate($rules);

        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Missing uploaded file.');
        }

        $directory = $fieldInstance->getDirectory() ?? '';
        $disk = $fieldInstance->getDisk();

        $stored = $upload->store($directory, ['disk' => $disk]);
        if (! is_string($stored) || $stored === '') {
            abort(HttpResponse::HTTP_INTERNAL_SERVER_ERROR, 'Could not persist uploaded file.');
        }

        $url = null;
        $filesystem = Storage::disk($disk);
        if (method_exists($filesystem, 'url')) {
            try {
                $url = $filesystem->url($stored);
            } catch (Throwable) {
                // disk does not support URL generation — leave null.
            }
        }

        return response()->json([
            'path' => $stored,
            'url' => $url,
            'size' => $upload->getSize(),
            'originalName' => $upload->getClientOriginalName(),
        ]);
    }

    public function destroy(Request $request, string $resource, string $field): JsonResponse
    {
        $instance = $this->resolveResourceOrFail($resource);
        $fieldInstance = $this->resolveFieldOrFail($instance, $field);

        if (! $fieldInstance instanceof FileField) {
            abort(HttpResponse::HTTP_BAD_REQUEST, 'Field is not a file upload.');
        }

        $path = $request->input('path');
        if (! is_string($path) || $path === '') {
            abort(HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Missing file path.');
        }

        Storage::disk($fieldInstance->getDisk())->delete($path);

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array<int, string>
     */
    private function buildFileRules(FileField $field): array
    {
        $rules = ['required', 'file'];

        $maxSize = $field->getMaxSize();
        if ($maxSize !== null) {
            $rules[] = 'max:'.$maxSize;
        }

        $accepted = $field->getAcceptedFileTypes();
        if ($accepted !== []) {
            $rules[] = 'mimetypes:'.implode(',', $accepted);
        }

        return $rules;
    }

    private function resolveResourceOrFail(string $slug): Resource
    {
        $class = $this->registry->findBySlug($slug);

        if ($class === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        /** @var resource $instance */
        $instance = app($class);

        return $instance;
    }

    private function resolveFieldOrFail(Resource $resource, string $name): Field
    {
        foreach ($resource->fields() as $field) {
            if ($field instanceof Field && $field->getName() === $name) {
                return $field;
            }
        }

        abort(HttpResponse::HTTP_NOT_FOUND);
    }
}
