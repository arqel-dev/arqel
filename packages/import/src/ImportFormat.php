<?php

declare(strict_types=1);

namespace Arqel\Import;

use InvalidArgumentException;

enum ImportFormat: string
{
    case CSV = 'csv';
    case XLSX = 'xlsx';

    public function extension(): string
    {
        return $this->value;
    }

    public static function fromExtension(string $ext): self
    {
        return self::tryFrom(strtolower($ext))
            ?? throw new InvalidArgumentException("Unsupported import format [{$ext}].");
    }
}
