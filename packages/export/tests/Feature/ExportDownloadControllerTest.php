<?php

declare(strict_types=1);

use Arqel\Export\Http\Controllers\ExportDownloadController;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/arqel-export-controller-'.bin2hex(random_bytes(4));
    mkdir($this->tempDir, 0o755, true);

    config()->set('arqel-export.destination_dir', $this->tempDir);
});

afterEach(function (): void {
    if (is_dir($this->tempDir)) {
        foreach (glob($this->tempDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tempDir);
    }
});

it('returns a BinaryFileResponse for a valid export id', function (): void {
    $exportId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    $path = $this->tempDir.'/export-'.$exportId.'.csv';
    file_put_contents($path, "id,name\n1,Alice\n");

    $controller = new ExportDownloadController;
    $response = $controller->download($exportId, Request::create('/admin/exports/'.$exportId.'/download'));

    expect($response)->toBeInstanceOf(BinaryFileResponse::class);
    expect($response->getFile()->getPathname())->toBe($path);
    expect($response->getStatusCode())->toBe(200);
});

it('aborts with 400 when the export id has invalid format', function (): void {
    $controller = new ExportDownloadController;

    expect(fn () => $controller->download('NOT-A-VALID-ID!!', Request::create('/x')))
        ->toThrow(HttpException::class);

    try {
        $controller->download('NOT-A-VALID-ID!!', Request::create('/x'));
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(400);
    }
});

it('aborts with 404 when the file does not exist', function (): void {
    $controller = new ExportDownloadController;

    try {
        $controller->download('11111111-1111-1111-1111-111111111111', Request::create('/x'));
        $this->fail('Expected HttpException');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404);
    }
});

it('aborts with 404 when multiple files match the export id', function (): void {
    $exportId = '22222222-2222-2222-2222-222222222222';
    file_put_contents($this->tempDir.'/export-'.$exportId.'.csv', 'a');
    file_put_contents($this->tempDir.'/export-'.$exportId.'.pdf', 'b');

    $controller = new ExportDownloadController;

    try {
        $controller->download($exportId, Request::create('/x'));
        $this->fail('Expected HttpException');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404);
    }
});
