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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
 * it from the configured disk.
 *
 * Authorization (#128): beyond the panel middleware (authentication),
 * both endpoints gate against the *owner* Resource's Policy — `create`
 * for uploads and `delete` for removals — and honour the field's own
 * `canBeEditedBy()` oracle. When no Policy is registered for the model
 * the gate silently allows (scaffold mode), mirroring
 * `ResourceController::authorize()`. The DELETE handler additionally
 * constrains the supplied `path` to the field's configured directory
 * so it can never be used to delete arbitrary files on the disk.
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

        $this->authorize($instance, 'create', $request);
        $this->authorizeField($fieldInstance);

        $rules = ['file' => $this->buildFileRules($fieldInstance)];
        $request->validate($rules);

        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Missing uploaded file.');
        }

        $disk = $fieldInstance->getDisk();

        // Reuse the field's single store implementation (#245) so the direct-
        // upload endpoint and the main-form write pipeline persist files the
        // exact same way — same disk, directory, hashName, and visibility ACL
        // (visibility matters on a private-default disk, see #142).
        try {
            $stored = $fieldInstance->storeUploadedFile($upload);
        } catch (Throwable) {
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

        $this->authorize($instance, 'delete', $request);
        $this->authorizeField($fieldInstance);

        $path = $request->input('path');
        if (! is_string($path) || $path === '') {
            abort(HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Missing file path.');
        }

        $path = $this->containedPathOrFail($fieldInstance, $path);

        Storage::disk($fieldInstance->getDisk())->delete($path);

        return response()->json(['deleted' => true]);
    }

    /**
     * Gate the operation against the owner Resource's Policy. Mirrors
     * `ResourceController::authorize()`: when no Policy is registered
     * for the model (and no matching ability gate exists) we silently
     * allow so scaffold ("Hello World") usage keeps working. The
     * `update` ability is consulted as a fallback so a Resource that
     * only exposes `update` still guards both upload and delete.
     */
    private function authorize(Resource $resource, string $ability, Request $request): void
    {
        $modelClass = $resource::getModel();
        $user = $request->user();

        foreach ([$ability, 'update'] as $candidate) {
            if (! Gate::has($candidate) && ! Gate::getPolicyFor($modelClass)) {
                continue;
            }

            if (Gate::forUser($user)->denies($candidate, $modelClass)) {
                abort(HttpResponse::HTTP_FORBIDDEN);
            }

            return;
        }
    }

    /**
     * Honour the field's own `canBeEditedBy()` oracle (HasAuthorization
     * trait, mirrors #102). Aborts 403 on deny. The record is unknown
     * at upload time, so only the user-level edit gate is consulted.
     */
    private function authorizeField(FileField $field): void
    {
        if (! $field->canBeEditedBy(Auth::user())) {
            abort(HttpResponse::HTTP_FORBIDDEN);
        }
    }

    /**
     * Reject any `path` that escapes the field's configured directory.
     * Absolute paths, `..` traversal segments, and null bytes are
     * refused; the returned value is the normalised relative path that
     * is safe to hand to the disk's `delete()`.
     */
    private function containedPathOrFail(FileField $field, string $path): string
    {
        if (str_contains($path, "\0")) {
            abort(HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'Invalid file path.');
        }

        // Reject absolute paths (POSIX `/...` and Windows `C:\...`).
        if (str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('#^[A-Za-z]:#', $path) === 1) {
            abort(HttpResponse::HTTP_FORBIDDEN, 'File path is outside the allowed directory.');
        }

        $normalised = str_replace('\\', '/', $path);
        $segments = [];
        foreach (explode('/', $normalised) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                abort(HttpResponse::HTTP_FORBIDDEN, 'File path is outside the allowed directory.');
            }

            $segments[] = $segment;
        }

        $clean = implode('/', $segments);

        $directory = trim($field->getDirectory() ?? '', '/');
        if ($directory !== '') {
            $prefix = $directory.'/';
            if ($clean !== $directory && ! str_starts_with($clean, $prefix)) {
                abort(HttpResponse::HTTP_FORBIDDEN, 'File path is outside the allowed directory.');
            }
        }

        return $clean;
    }

    /**
     * Delegate the per-file rules to the field itself so the HTTP upload
     * path honours the exact rule set the main-form `uploadRule()` closure
     * applies — crucially `ImageField`'s `image` gate, which the previous
     * hard-coded `['required','file']` ignored (#166-B). A direct upload is
     * always a present file, so `required` is prepended.
     *
     * @return array<int, string>
     */
    private function buildFileRules(FileField $field): array
    {
        return array_merge(['required'], $field->uploadFileRules());
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
        foreach ($resource->effectiveFields() as $field) {
            if ($field instanceof Field && $field->getName() === $name) {
                return $field;
            }
        }

        abort(HttpResponse::HTTP_NOT_FOUND);
    }
}
