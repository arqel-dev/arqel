<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

/**
 * Image upload input.
 *
 * Defaults the mime gate to common image types and adds optional
 * client-side crop ratio + server-side resize target width.
 * Actual cropping/resizing is the React layer + CORE-006 upload
 * handler; this field only carries the intent.
 */
final class ImageField extends FileField
{
    protected string $type = 'image';

    protected string $component = 'ImageInput';

    /** @var array<int, string> */
    protected array $acceptedFileTypes = ['image/jpeg', 'image/png', 'image/webp'];

    protected ?string $imageCropAspectRatio = null;

    protected ?int $imageResizeTargetWidth = null;

    public function imageCropAspectRatio(string $ratio): static
    {
        $this->imageCropAspectRatio = $ratio;

        return $this;
    }

    public function imageResizeTargetWidth(int $pixels): static
    {
        $this->imageResizeTargetWidth = $pixels;

        return $this;
    }

    public function getImageCropAspectRatio(): ?string
    {
        return $this->imageCropAspectRatio;
    }

    public function getImageResizeTargetWidth(): ?int
    {
        return $this->imageResizeTargetWidth;
    }

    /**
     * @return array<int, string>
     */
    public function getDefaultRules(): array
    {
        return $this->multiple ? ['array'] : ['image'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            ...parent::getTypeSpecificProps(),
            'imageCropAspectRatio' => $this->imageCropAspectRatio,
            'imageResizeTargetWidth' => $this->imageResizeTargetWidth,
        ], fn ($value) => $value !== null);
    }
}
