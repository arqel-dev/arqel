<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;

/**
 * Guards i18n para as mensagens user-facing dos controllers do marketplace
 * (Round 9). Cada literal antes hardcoded em inglês passa agora por
 * `__('arqel::messages.marketplace.*')`; estes testes asseguram que as
 * chaves resolvem (não vazam a chave dotted crua) e que existem em ambos
 * os locales com os valores corretos.
 */
$keys = [
    'forbidden' => 'Forbidden',
    'unauthenticated' => 'Unauthenticated',
    'validation_failed' => 'Validation failed',
    'license_required' => 'License required',
    'purchase_not_found' => 'Purchase not found',
    'review_not_found' => 'Review not found',
    'refund_failed' => 'Refund failed at gateway',
    'payment_verification_failed' => 'Payment verification failed',
];

it('resolves every marketplace message key in english (no raw key leak)', function () use ($keys): void {
    App::setLocale('en');

    foreach ($keys as $suffix => $english) {
        $key = "arqel::messages.marketplace.{$suffix}";
        $value = __($key);

        expect($value)->toBe($english);
        // A chave dotted crua nunca deve vazar para a resposta JSON.
        expect($value)->not->toBe($key);
    }
});

it('translates every marketplace message key to pt_BR', function (): void {
    App::setLocale('pt_BR');

    $expected = [
        'forbidden' => 'Acesso negado',
        'unauthenticated' => 'Não autenticado',
        'validation_failed' => 'Falha na validação',
        'license_required' => 'Licença obrigatória',
        'purchase_not_found' => 'Compra não encontrada',
        'review_not_found' => 'Avaliação não encontrada',
        'refund_failed' => 'Falha no reembolso pelo gateway',
        'payment_verification_failed' => 'Falha na verificação do pagamento',
    ];

    foreach ($expected as $suffix => $ptBr) {
        $key = "arqel::messages.marketplace.{$suffix}";
        $value = __($key);

        expect($value)->toBe($ptBr);
        expect($value)->not->toBe($key);
    }
});
