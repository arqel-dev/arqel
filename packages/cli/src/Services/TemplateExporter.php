<?php

declare(strict_types=1);

namespace Arqel\Cli\Services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Exporta um diretório de template para um destino aplicando substituições
 * em arquivos textuais. Pula binários conhecidos via blocklist de extensões.
 *
 * @phpstan-type Replacements array<string, string>
 */
final readonly class TemplateExporter
{
    /**
     * Extensões consideradas textuais (sujeitas a placeholder replacement).
     *
     * @var list<string>
     */
    private const array TEXT_EXTENSIONS = [
        'yml', 'yaml', 'json', 'md', 'php', 'env', 'example',
        'gitignore', 'gitattributes', 'editorconfig', 'txt',
        'lock', 'neon', 'xml', 'js', 'ts', 'tsx', 'jsx', 'css',
        'html', 'sh', 'ps1', 'toml',
    ];

    /**
     * Extensões binárias — copiadas sem replacement.
     *
     * @var list<string>
     */
    private const array BINARY_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'svg',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'zip', 'tar', 'gz', 'bz2', 'rar', '7z',
        'pdf', 'mp3', 'mp4', 'webm', 'wav',
        'sqlite', 'sqlite3', 'db',
    ];

    /**
     * @param  array<string, string>  $replacements  placeholder → valor
     */
    public function __construct(
        public string $sourceDir,
        public string $targetDir,
        public array $replacements,
    ) {}

    /**
     * Executa a exportação. Retorna a lista absoluta dos arquivos escritos.
     *
     * @return list<string>
     */
    public function export(): array
    {
        if (! is_dir($this->sourceDir)) {
            throw new RuntimeException("Source directory not found: {$this->sourceDir}");
        }

        if (! is_dir($this->targetDir) && ! mkdir($this->targetDir, 0o755, true) && ! is_dir($this->targetDir)) {
            throw new RuntimeException("Unable to create target directory: {$this->targetDir}");
        }

        $written = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->sourceDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        $sourceLen = strlen(rtrim($this->sourceDir, DIRECTORY_SEPARATOR)) + 1;

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), $sourceLen);
            if ($relative === '') {
                continue;
            }

            $destination = $this->targetDir.DIRECTORY_SEPARATOR.$relative;

            if ($file->isDir()) {
                if (! is_dir($destination) && ! mkdir($destination, 0o755, true) && ! is_dir($destination)) {
                    throw new RuntimeException("Unable to create directory: {$destination}");
                }

                continue;
            }

            $parent = dirname($destination);
            if (! is_dir($parent) && ! mkdir($parent, 0o755, true) && ! is_dir($parent)) {
                throw new RuntimeException("Unable to create directory: {$parent}");
            }

            $this->writeFile($file, $destination);
            $written[] = $destination;
        }

        sort($written);

        return $written;
    }

    private function writeFile(SplFileInfo $source, string $destination): void
    {
        if (self::isBinary($source)) {
            if (! copy($source->getPathname(), $destination)) {
                throw new RuntimeException("Failed to copy binary file: {$source->getPathname()}");
            }
            $perms = $source->getPerms();
            if ($perms !== false) {
                @chmod($destination, $perms & 0o777);
            }

            return;
        }

        $contents = file_get_contents($source->getPathname());
        if ($contents === false) {
            throw new RuntimeException("Failed to read file: {$source->getPathname()}");
        }

        if ($this->replacements !== []) {
            $contents = strtr($contents, $this->replacements);
        }

        if (file_put_contents($destination, $contents) === false) {
            throw new RuntimeException("Failed to write file: {$destination}");
        }

        $perms = $source->getPerms();
        if ($perms !== false) {
            @chmod($destination, $perms & 0o777);
        }
    }

    private static function isBinary(SplFileInfo $file): bool
    {
        $ext = strtolower($file->getExtension());
        if ($ext === '') {
            // Filename-based heuristic for dotfiles like `.gitignore`.
            $name = ltrim($file->getFilename(), '.');
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext === '') {
                $ext = strtolower($name);
            }
        }

        if (in_array($ext, self::BINARY_EXTENSIONS, true)) {
            return true;
        }

        if (in_array($ext, self::TEXT_EXTENSIONS, true)) {
            return false;
        }

        // Default: treat unknown extension as text but skip replacement-sensitive
        // null-byte content. Conservative — readable templates only.
        return false;
    }
}
