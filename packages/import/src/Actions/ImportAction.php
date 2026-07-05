<?php

declare(strict_types=1);

namespace Arqel\Import\Actions;

use Arqel\Actions\Action;
use Arqel\Import\Importer;
use Arqel\Import\ImportFormat;

/**
 * Header/toolbar action that opens the import upload flow for a Resource.
 *
 * Extends the framework Action so it inherits the per-action authorization
 * gate (`authorize('import')` / the Resource policy) at every entry point.
 */
final class ImportAction extends Action
{
    protected string $type = 'toolbar';

    private ImportFormat $format = ImportFormat::CSV;

    /** @var class-string<Importer>|null */
    private ?string $importerClass = null;

    public static function make(string $name): static
    {
        $action = new self($name);
        $action->label('arqel::import.action');
        $action->icon('upload');

        return $action;
    }

    /** @param class-string<Importer> $class */
    public function importer(string $class): self
    {
        $this->importerClass = $class;

        return $this;
    }

    public function format(ImportFormat $format): self
    {
        $this->format = $format;

        return $this;
    }

    /** @return class-string<Importer>|null */
    public function getImporterClass(): ?string
    {
        return $this->importerClass;
    }

    public function getFormat(): ImportFormat
    {
        return $this->format;
    }
}
