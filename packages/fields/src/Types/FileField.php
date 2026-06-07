<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

use Arqel\Fields\Field;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * File upload input.
 *
 * Today this class captures the storage configuration (disk,
 * directory, visibility, mime gate, max size, multiple flag, reorder
 * flag, upload strategy). Actual upload handling (`handleUpload`,
 * `handleDelete`) and the generated `POST` endpoint are wired up
 * once CORE-006 ships the resource controller; until then the
 * field exposes the configuration in `getTypeSpecificProps()` so
 * the React component knows how to render and where to POST.
 *
 * Subclassed by `ImageField`.
 */
class FileField extends Field
{
    public const string STRATEGY_DIRECT = 'direct';

    public const string STRATEGY_SPATIE_MEDIA_LIBRARY = 'spatie-media-library';

    public const string STRATEGY_PRESIGNED = 'presigned';

    public const string VISIBILITY_PRIVATE = 'private';

    public const string VISIBILITY_PUBLIC = 'public';

    protected string $type = 'file';

    protected string $component = 'FileInput';

    protected string $disk = 'local';

    protected ?string $directory = null;

    protected string $visibility = self::VISIBILITY_PRIVATE;

    protected ?int $maxSize = null;

    /** @var array<int, string> */
    protected array $acceptedFileTypes = [];

    protected bool $multiple = false;

    protected bool $reorderable = false;

    protected string $strategy = self::STRATEGY_DIRECT;

    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function directory(string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function visibility(string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function maxSize(int $kilobytes): static
    {
        $this->maxSize = $kilobytes;

        return $this;
    }

    /**
     * @param array<int, string> $mimeTypes
     */
    public function acceptedFileTypes(array $mimeTypes): static
    {
        $this->acceptedFileTypes = array_values($mimeTypes);

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function reorderable(bool $reorderable = true): static
    {
        $this->reorderable = $reorderable;
        if ($reorderable) {
            $this->multiple = true;
        }

        return $this;
    }

    public function using(string $strategy): static
    {
        $this->strategy = $strategy;

        return $this;
    }

    public function getDisk(): string
    {
        return $this->disk;
    }

    public function getDirectory(): ?string
    {
        return $this->directory;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function getMaxSize(): ?int
    {
        return $this->maxSize;
    }

    /** @return array<int, string> */
    public function getAcceptedFileTypes(): array
    {
        return $this->acceptedFileTypes;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function isReorderable(): bool
    {
        return $this->reorderable;
    }

    public function getStrategy(): string
    {
        return $this->strategy;
    }

    /**
     * @return array<int, string|Closure>
     */
    public function getDefaultRules(): array
    {
        if ($this->multiple) {
            return ['array'];
        }

        return [$this->uploadRule()];
    }

    /**
     * Build the single-file validation rule.
     *
     * The frontend re-submits the record's stored attribute — the path
     * STRING — when a populated file field is edited without re-uploading
     * (#150). The bare `file` rule rejects any non-`UploadedFile`, and
     * `nullable` does not skip a non-null string, so saving 422'd. We emit a
     * closure that:
     *
     *   - passes for a stored-path string (the unchanged value) and for an
     *     empty/null value (the optional/required gate is enforced separately
     *     by the injected `nullable`/`required` rule);
     *   - runs the real upload checks (`file`/`image` + `max` + `mimetypes`)
     *     when the submitted value IS an actual `UploadedFile`, so genuine
     *     uploads are never under-validated;
     *   - rejects any other non-string scalar/array (e.g. an int) that is
     *     neither a stored path nor an upload.
     */
    protected function uploadRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            // Unchanged stored path on edit, or an empty/null value: the
            // upload-implying checks do not apply. Required-ness is enforced
            // by the separate required/nullable rule.
            if ($value === null || $value === '' || is_string($value)) {
                return;
            }

            // An actual upload (or any other non-string value, e.g. an int)
            // is run through the real file rules. A non-`UploadedFile` value
            // is rejected by the `file`/`image` rule, so this still fails for
            // a value that is neither a stored path nor a genuine upload.
            $validator = Validator::make(
                [$attribute => $value],
                [$attribute => $this->uploadFileRules()],
            );

            foreach ($validator->errors()->get($attribute) as $message) {
                $fail($message);
            }
        };
    }

    /**
     * The Laravel rules applied to an actual uploaded file. Overridden by
     * `ImageField` to gate on `image` rather than `file`.
     *
     * @return array<int, string>
     */
    protected function uploadFileRules(): array
    {
        $rules = ['file'];

        if ($this->maxSize !== null) {
            $rules[] = 'max:'.$this->maxSize;
        }

        if ($this->acceptedFileTypes !== []) {
            $rules[] = 'mimetypes:'.implode(',', $this->acceptedFileTypes);
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            'disk' => $this->disk,
            'directory' => $this->directory,
            'visibility' => $this->visibility,
            'maxSize' => $this->maxSize,
            'acceptedFileTypes' => $this->acceptedFileTypes !== [] ? $this->acceptedFileTypes : null,
            'multiple' => $this->multiple,
            'reorderable' => $this->reorderable,
            'strategy' => $this->strategy,
        ], fn ($value) => $value !== null);
    }
}
