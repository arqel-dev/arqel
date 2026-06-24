<?php

declare(strict_types=1);

use Arqel\Tenant\Exceptions\TenantNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Round 15 i18n regression for the resolver-failure 404 message.
 *
 * The constructor default must route through `arqel::messages.tenant.not_resolved`
 * so it honours the request locale. English value is pinned for stability
 * (the prior hardcoded literal); pt_BR is pinned to prove it is localized.
 */
it('uses the localized default message in pt_BR when none is supplied', function (): void {
    app()->setLocale('pt_BR');

    $exception = new TenantNotFoundException;

    expect($exception->getMessage())
        ->toBe(__('arqel::messages.tenant.not_resolved', [], 'pt_BR'))
        ->and($exception->getMessage())->not->toBe('arqel::messages.tenant.not_resolved');
});

it('keeps the English default message identical to the original literal', function (): void {
    app()->setLocale('en');

    expect((new TenantNotFoundException)->getMessage())
        ->toBe('No tenant could be resolved for the request.')
        ->and(__('arqel::messages.tenant.not_resolved', [], 'en'))
        ->toBe('No tenant could be resolved for the request.');
});

it('still honours an explicitly supplied message', function (): void {
    app()->setLocale('pt_BR');

    expect((new TenantNotFoundException('custom failure'))->getMessage())
        ->toBe('custom failure');
});

it('serializes the localized message into the JSON 404 payload', function (): void {
    app()->setLocale('pt_BR');

    $exception = new TenantNotFoundException(identifier: 'acme');
    $request = Request::create('/dashboard', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = $exception->render($request);

    expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) $response->getContent(), true);

    expect($decoded['message'])
        ->toBe(__('arqel::messages.tenant.not_resolved', [], 'pt_BR'))
        ->and($decoded['tenantIdentifier'])->toBe('acme');
});
