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
    'purchase_already_refunded' => 'Purchase is already refunded.',
    'refund_only_completed' => 'Only completed purchases can be refunded.',
    'plugin_is_free' => 'Plugin is free.',
    'payment_id_required' => 'paymentId is required.',
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
        'purchase_already_refunded' => 'A compra já foi reembolsada.',
        'refund_only_completed' => 'Apenas compras concluídas podem ser reembolsadas.',
        'plugin_is_free' => 'O plugin é gratuito.',
        'payment_id_required' => 'paymentId é obrigatório.',
    ];

    foreach ($expected as $suffix => $ptBr) {
        $key = "arqel::messages.marketplace.{$suffix}";
        $value = __($key);

        expect($value)->toBe($ptBr);
        expect($value)->not->toBe($key);
    }
});

it('localizes the controllers nested validation-error detail strings under pt_BR', function (): void {
    App::setLocale('pt_BR');

    // Detalhes de erro per-field antes hardcoded em inglês nos controllers
    // AdminRefundController + PluginPurchaseController (Round 15).
    expect(__('arqel::messages.marketplace.purchase_already_refunded'))
        ->toBe('A compra já foi reembolsada.')
        ->not->toBe('Purchase is already refunded.');
    expect(__('arqel::messages.marketplace.refund_only_completed'))
        ->toBe('Apenas compras concluídas podem ser reembolsadas.')
        ->not->toBe('Only completed purchases can be refunded.');
    expect(__('arqel::messages.marketplace.plugin_is_free'))
        ->toBe('O plugin é gratuito.')
        ->not->toBe('Plugin is free.');
    expect(__('arqel::messages.marketplace.payment_id_required'))
        ->toBe('paymentId é obrigatório.')
        ->not->toBe('paymentId is required.');
});

it('interpolates :slug in the plugin/category not-found messages (en)', function (): void {
    App::setLocale('en');

    expect(__('arqel::messages.marketplace.plugin_not_found', ['slug' => 'my-plugin']))
        ->toBe('Plugin [my-plugin] not found');
    expect(__('arqel::messages.marketplace.category_not_found', ['slug' => 'widgets']))
        ->toBe('Category [widgets] not found');
});

it('interpolates :slug in the plugin/category not-found messages (pt_BR)', function (): void {
    App::setLocale('pt_BR');

    expect(__('arqel::messages.marketplace.plugin_not_found', ['slug' => 'my-plugin']))
        ->toBe('Plugin [my-plugin] não encontrado');
    expect(__('arqel::messages.marketplace.category_not_found', ['slug' => 'widgets']))
        ->toBe('Categoria [widgets] não encontrada');
});
