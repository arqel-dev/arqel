<?php

declare(strict_types=1);

use Arqel\Cli\Services\TemplateExporter;

function makeTmpDir(string $suffix): string
{
    $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arqel-tplexp-'.$suffix.'-'.bin2hex(random_bytes(4));
    mkdir($base, 0o755, true);

    return $base;
}

function rmrf(string $path): void
{
    if (! file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);

        return;
    }
    foreach ((array) scandir($path) as $entry) {
        if ($entry === '.' || $entry === '..' || ! is_string($entry)) {
            continue;
        }
        rmrf($path.DIRECTORY_SEPARATOR.$entry);
    }
    @rmdir($path);
}

it('exports a simple file applying replacements', function (): void {
    $src = makeTmpDir('src');
    $dst = makeTmpDir('dst');
    file_put_contents($src.'/hello.txt', 'Hello, {{APP_NAME}}!');

    try {
        $exporter = new TemplateExporter($src, $dst, ['{{APP_NAME}}' => 'arqel-app']);
        $written = $exporter->export();

        expect($written)->toHaveCount(1);
        expect(file_get_contents($dst.'/hello.txt'))->toBe('Hello, arqel-app!');
    } finally {
        rmrf($src);
        rmrf($dst);
    }
});

it('skips placeholder replacement on binary extensions', function (): void {
    $src = makeTmpDir('src');
    $dst = makeTmpDir('dst');
    $bytes = "\x89PNG\r\n\x1a\n{{APP_NAME}}";
    file_put_contents($src.'/logo.png', $bytes);

    try {
        $exporter = new TemplateExporter($src, $dst, ['{{APP_NAME}}' => 'never']);
        $exporter->export();

        expect(file_get_contents($dst.'/logo.png'))->toBe($bytes);
    } finally {
        rmrf($src);
        rmrf($dst);
    }
});

it('preserves nested subdirectory structure', function (): void {
    $src = makeTmpDir('src');
    $dst = makeTmpDir('dst');
    mkdir($src.'/app/Providers', 0o755, true);
    file_put_contents($src.'/app/Providers/Service.php', '<?php // {{APP_NAME}}');
    file_put_contents($src.'/composer.json', '{"name":"{{APP_NAME}}"}');

    try {
        $exporter = new TemplateExporter($src, $dst, ['{{APP_NAME}}' => 'demo']);
        $written = $exporter->export();

        expect(is_dir($dst.'/app/Providers'))->toBeTrue();
        expect(file_get_contents($dst.'/app/Providers/Service.php'))->toBe('<?php // demo');
        expect(file_get_contents($dst.'/composer.json'))->toBe('{"name":"demo"}');
        expect($written)->toHaveCount(2);
    } finally {
        rmrf($src);
        rmrf($dst);
    }
});

it('applies replacements across multiple files', function (): void {
    $src = makeTmpDir('src');
    $dst = makeTmpDir('dst');
    file_put_contents($src.'/cloud.yml', "name: {{APP_NAME}}\nenv: {{APP_ENV}}");
    file_put_contents($src.'/.env.example', "APP_NAME={{APP_NAME}}\nAPP_ENV={{APP_ENV}}");

    try {
        $exporter = new TemplateExporter($src, $dst, [
            '{{APP_NAME}}' => 'multi-app',
            '{{APP_ENV}}' => 'production',
        ]);
        $exporter->export();

        expect(file_get_contents($dst.'/cloud.yml'))->toContain('name: multi-app');
        expect(file_get_contents($dst.'/cloud.yml'))->toContain('env: production');
        expect(file_get_contents($dst.'/.env.example'))->toContain('APP_NAME=multi-app');
        expect(file_get_contents($dst.'/.env.example'))->toContain('APP_ENV=production');
    } finally {
        rmrf($src);
        rmrf($dst);
    }
});

it('returns sorted absolute paths for written files', function (): void {
    $src = makeTmpDir('src');
    $dst = makeTmpDir('dst');
    file_put_contents($src.'/b.md', 'b');
    file_put_contents($src.'/a.md', 'a');

    try {
        $exporter = new TemplateExporter($src, $dst, []);
        $written = $exporter->export();

        expect($written)->toHaveCount(2);
        expect($written[0])->toEndWith('a.md');
        expect($written[1])->toEndWith('b.md');
        foreach ($written as $path) {
            expect(str_starts_with($path, $dst))->toBeTrue();
        }
    } finally {
        rmrf($src);
        rmrf($dst);
    }
});

it('creates target directory if it does not exist', function (): void {
    $src = makeTmpDir('src');
    $dstParent = makeTmpDir('dstp');
    $dst = $dstParent.'/nested/output';
    file_put_contents($src.'/file.txt', 'content');

    try {
        $exporter = new TemplateExporter($src, $dst, []);
        $exporter->export();

        expect(is_dir($dst))->toBeTrue();
        expect(file_exists($dst.'/file.txt'))->toBeTrue();
    } finally {
        rmrf($src);
        rmrf($dstParent);
    }
});
