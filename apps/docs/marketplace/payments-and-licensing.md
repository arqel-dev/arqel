# Pagamentos & licenças

> Como precificar plugins, gerar license keys, processar payouts e lidar com refunds no Arqel Marketplace.

Esta página cobre o ciclo financeiro completo de um plugin pago — do `price_cents` na row de submission até o payout mensal para o publisher. Ela documenta o que está entregue (MKTPLC-008) e o que ainda é placeholder até o follow-up Stripe Connect real.

## Estado atual

| Componente | Status |
|---|---|
| Schema de purchase + license + payout | ✅ Entregue (MKTPLC-008) |
| `MockPaymentGateway` (default para dev/test) | ✅ Entregue |
| `LicenseKeyGenerator` formato `ARQ-XXXX-XXXX-XXXX-XXXX` | ✅ Entregue |
| Endpoints `purchase` / `confirm` / `download` / `refund` | ✅ Entregue |
| `StripeConnectGateway` real | ✅ Entregue — opt-in via `composer require stripe/stripe-php` + config `payment_gateway=stripe` |
| Webhooks Stripe (idempotência, refund.updated) | ⏳ Próximo follow-up |
| Payouts cron job (mensal, threshold $50) | ⏳ Schema pronto, scheduler é TODO follow-up |
| Tax invoicing | ⏳ Out of scope da Arqel — publisher é responsável |

Por padrão o pacote bind `PaymentGateway => MockPaymentGateway`. Apps host (incluindo `arqel.dev/marketplace` em production) rebindam para gateway real quando estiver disponível:

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->bind(
        \Arqel\Marketplace\Contracts\PaymentGateway::class,
        \App\Marketplace\StripeConnectGateway::class,
    );
}
```

## Configurando Stripe Connect

O `StripeConnectGateway` real é opt-in — apps que querem cobrar plugins reais ativam o gateway via dependência adicional + config. O SDK `stripe/stripe-php` adiciona ~200KB ao vendor, então o pacote `arqel/marketplace` declara em `suggest` (não `require`). Apps que usam apenas plugins gratuitos não pagam o custo.

### Pré-requisitos

1. **Conta Stripe ativa** com Connect habilitado. Acesse [dashboard.stripe.com/connect](https://dashboard.stripe.com/connect/overview) e siga o onboarding de plataforma. Cobertura: USD, BRL, EUR, GBP e mais 130 currencies.
2. **Plano de Connect** — Standard, Express ou Custom. Para o marketplace Arqel padrão, recomendamos **Express** (menor friction de onboarding para publishers; Stripe hostea o KYC).
3. **Webhook endpoint público** — Stripe entrega eventos via HTTPS. Se você está em dev, use [Stripe CLI](https://stripe.com/docs/stripe-cli) (`stripe listen --forward-to localhost:8000/stripe/webhook`).

### Instalação

```bash
composer require stripe/stripe-php
```

Verifique a instalação:

```bash
composer show stripe/stripe-php
# stripe/stripe-php  v16.x.x  Stripe PHP Library
```

### Config + env vars

No `.env` da app:

```env
# Driver de gateway
ARQEL_MARKETPLACE_PAYMENT_GATEWAY=stripe

# Credenciais Stripe (use sk_test_... em dev)
STRIPE_SECRET=sk_live_51xxxxx
STRIPE_PLATFORM_ACCOUNT_ID=acct_platformxxxxxx
STRIPE_PLATFORM_FEE_PERCENT=20

# URLs de retorno (Stripe Checkout redireciona pra cá após pagamento)
STRIPE_SUCCESS_URL=https://arqel.dev/marketplace/checkout/success?session_id={CHECKOUT_SESSION_ID}
STRIPE_CANCEL_URL=https://arqel.dev/marketplace/checkout/cancel
```

O `MarketplaceServiceProvider` lê esses valores em `register()` e instancia `StripeConnectGateway` automaticamente. Se `ARQEL_MARKETPLACE_PAYMENT_GATEWAY=stripe` mas o SDK não estiver instalado, o provider faz fallback para `MockPaymentGateway` e loga um warning — assim a app não quebra em CI/dev sem o SDK.

### Onboarding de publishers (Connect)

Cada publisher que quer vender plugins precisa de uma **Connect account** vinculada à sua conta de plataforma. O fluxo recomendado é Express (Stripe-hosted):

1. Publisher clica em "Set up payouts" no admin do Arqel.
2. App cria uma Connect Express account via Stripe API:

   ```php
   use Stripe\StripeClient;

   $stripe = new StripeClient(config('arqel-marketplace.stripe.secret'));
   $account = $stripe->accounts->create([
       'type' => 'express',
       'country' => 'BR',
       'email' => $publisher->email,
       'capabilities' => [
           'card_payments' => ['requested' => true],
           'transfers' => ['requested' => true],
       ],
   ]);
   ```

3. App cria account link e redireciona o publisher para Stripe-hosted onboarding:

   ```php
   $link = $stripe->accountLinks->create([
       'account' => $account->id,
       'refresh_url' => 'https://arqel.dev/publisher/stripe/refresh',
       'return_url' => 'https://arqel.dev/publisher/stripe/return',
       'type' => 'account_onboarding',
   ]);

   return redirect($link->url);
   ```

4. Após onboarding, persista o `account->id` em `arqel_plugins.publisher_stripe_account_id` (column adicionada pela migration `2026_05_07_000000_add_publisher_stripe_to_arqel_plugins.php`). A partir daí, todo checkout do plugin dispara `application_fee_amount` (Arqel cut, default 20%) + `transfer_data.destination` (publisher account).

> Plugins **sem** `publisher_stripe_account_id` preenchido continuam funcionando — o pagamento todo fica na plataforma. Útil para plugins próprios da Arqel ou enquanto o publisher ainda não completou onboarding.

### Testando com test cards

Em dev/staging use `STRIPE_SECRET=sk_test_...`. Stripe oferece test cards previsíveis para simular cenários:

| Número | Resultado |
|---|---|
| `4242 4242 4242 4242` | Success |
| `4000 0000 0000 0002` | Generic decline |
| `4000 0025 0000 3155` | Authentication required (3D Secure) |
| `4000 0000 0000 9995` | Insufficient funds |

Use qualquer CVC de 3 dígitos, qualquer ZIP de 5 dígitos, qualquer data futura. Lista completa em [stripe.com/docs/testing](https://stripe.com/docs/testing#cards).

Fluxo end-to-end em dev:

```bash
# Terminal 1: forward webhooks
stripe listen --forward-to localhost:8000/stripe/webhook

# Terminal 2: app rodando
php artisan serve

# No app:
# 1. Inicie purchase (POST /api/marketplace/plugins/{slug}/purchase)
# 2. Use a checkout.url retornada — Stripe vai mostrar form de teste
# 3. Cole 4242 4242 4242 4242 + qualquer CVC/ZIP/data futura
# 4. Stripe redireciona para STRIPE_SUCCESS_URL com session_id
# 5. App chama POST /api/marketplace/plugins/{slug}/purchase/confirm
```

### Troubleshooting comum

| Sintoma | Causa | Solução |
|---|---|---|
| `RuntimeException: stripe/stripe-php SDK not installed` | SDK não instalado mas gateway ativado | `composer require stripe/stripe-php` |
| Provider bind cai pra Mock + log warning | `payment_gateway=stripe` mas `class_exists(StripeClient)` false | Idem acima — verifique `composer show stripe/stripe-php` |
| `MarketplaceException: Failed to create Stripe checkout session` | Erro upstream do Stripe (auth, currency inválida, etc.) | Cheque logs em `storage/logs/laravel.log` — exception original vai como `previous` |
| Checkout não redireciona para publisher account | `publisher_stripe_account_id` null no plugin | Complete onboarding Connect; persista a column |
| Application fee parece errada | `STRIPE_PLATFORM_FEE_PERCENT` não bate com o esperado | Cast é `(int)`; valor é % do `price_cents`. Para 15% use `STRIPE_PLATFORM_FEE_PERCENT=15` |

## Pricing de um plugin

Cada row em `arqel_plugins` carrega quatro colunas relevantes para pricing:

| Coluna | Tipo | Default | Significado |
|---|---|---|---|
| `price_cents` | int | `0` | Preço em centavos da `currency`. `0` = free. |
| `currency` | string(3) | `USD` | ISO 4217. Use `EUR`, `BRL`, `GBP` etc. para preços localizados. |
| `publisher_user_id` | int nullable | populado na submission | FK para `users` — recipient dos payouts. |
| `revenue_share_percent` | int | `80` | % do `price_cents` que vai para o publisher; o restante (`100 - revenue_share_percent`) fica com a Arqel. |

O acessor `Plugin::isPremium()` retorna `price_cents > 0`. Esse boolean dispara o pipeline de checkout em vez do download direto.

### Como precificar

Recomendações para definir `price_cents`:

| Faixa | Tipo de plugin | Exemplo |
|---|---|---|
| Free (`0`) | Helpers genéricos, fields básicos, OSS pure | `arqel/fields-markdown` |
| `$5-$15` | Field-pack com integração de SDK terceiro | Stripe Card, Mapbox Address |
| `$20-$50` | Widget-pack ou integração crítica (Slack, Sentry) | Sentry dashboards widget |
| `$100+` | White-label themes ou plugins enterprise (custom auth, multi-tenant SSO) | Themes corporativos |

Pricing de subscriptions (mensal/anual) **não está implementado** em MKTPLC-008 — apenas one-time purchase. Subscription model entra em follow-up futuro.

### Atualização de preço

Preço pode ser atualizado via admin endpoint do publisher (futuro `PATCH /publisher/plugins/{slug}` com Gate `marketplace.update`). Aumentos de preço **não retroagem** — purchases existentes mantêm acesso permanente. Reduções de preço também não geram refund automático.

## License keys

Toda purchase completed gera uma license key única no formato:

```
ARQ-XXXX-XXXX-XXXX-XXXX
```

Onde cada `XXXX` é um grupo hex de 4 chars. Total: 16 hex chars = 64 bits de entropia (gerados via `random_bytes(8)` no `LicenseKeyGenerator::generate()`).

### Verificação

```php
use Arqel\Marketplace\Services\LicenseKeyGenerator;

$generator = app(LicenseKeyGenerator::class);

if ($generator->verify($licenseKey, $plugin)) {
    // license válida + status completed
}
```

`verify()` faz três checks:

1. Formato regex `/^ARQ-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}$/`.
2. Match contra `arqel_plugin_purchases.license_key` via `hash_equals` (timing-safe).
3. `status === 'completed'` (refunded ou pending bloqueia).

### Distribuição

A license key é retornada **apenas uma vez** no payload de `confirm`:

```http
POST /api/marketplace/plugins/{slug}/purchase/confirm
{ "paymentId": "mock_abc123" }

# response
{
  "purchase": {
    "id": 42,
    "status": "completed",
    "license_key": "ARQ-A1B2-C3D4-E5F6-7890",
    "purchased_at": "2026-05-01T14:23:00Z"
  }
}
```

O cliente do marketplace (CLI `arqel:install`) salva a key em `~/.arqel/credentials.json` (TODO da entrega `arqel/cli`). Re-buscar a key passa por `GET /publisher/purchases/{id}` (autenticado).

## Fluxo completo de purchase

### 1. Initiate

Authenticated user dispara compra:

```http
POST /api/marketplace/plugins/acme-stripe-card/purchase
Authorization: Bearer <user_token>
```

Resultado possível:

- **422** se plugin é free (`price_cents === 0`).
- **401** se não autenticado.
- **404** se plugin não published.
- **200 com `already_owned: true`** se user já tem purchase completed.
- **200 com `purchase + checkout`** caso contrário:

```json
{
  "purchase": { "id": 42, "status": "pending" },
  "checkout": {
    "url": "https://arqel.dev/marketplace/mock-checkout/acme-stripe-card",
    "session_id": "mock_abc123"
  }
}
```

A `checkout.url` redireciona o user para o gateway. No `MockPaymentGateway`, a URL é stub que apenas devolve o `session_id` direto. Em production com Stripe Connect, será uma URL real do Stripe Checkout.

### 2. Confirm (callback do gateway)

Após pagamento bem-sucedido, gateway chama (ou frontend chama com o session id retornado):

```http
POST /api/marketplace/plugins/acme-stripe-card/purchase/confirm
{ "paymentId": "mock_abc123" }
```

O `PluginPurchaseController::confirm`:

1. Verifica via `PaymentGateway::verifyPayment($paymentId)` — retorna `PaymentResult` (`success`, `amount`, `currency`).
2. Se sucesso, marca purchase como `completed`, persiste `payment_id`, gera license key via `LicenseKeyGenerator`.
3. Idempotente em re-confirm — se purchase já estiver `completed`, devolve mesma license key.

### 3. Download

Com purchase completed, user pode baixar:

```http
GET /api/marketplace/plugins/acme-stripe-card/download
Authorization: Bearer <user_token>
```

Free plugins liberam download sem checkar nada; premium exigem purchase completed. Sem purchase válida → **403 Forbidden**.

Resposta:

```json
{
  "download_url": "https://arqel.dev/marketplace/download/acme-stripe-card/latest.zip",
  "license_key": "ARQ-A1B2-C3D4-E5F6-7890"
}
```

## Refunds

Política Arqel: **refund até 14 dias** após purchase, sem questionamento (consumer protection alinhada com EU + Brazilian Código de Defesa do Consumidor).

Após 14 dias, refund é avaliado caso a caso pelo admin via Gate `marketplace.refund`:

```http
POST /api/marketplace/admin/plugins/{slug}/refund/{purchaseId}
Authorization: Bearer <admin_token>
```

O `AdminRefundController`:

1. Valida que purchase está `completed` (422 se já refunded ou pending).
2. Chama `PaymentGateway::processRefund($purchase->payment_id, $purchase->amount_cents)`.
3. Marca purchase como `refunded` + popula `refunded_at`.
4. License key fica invalidada — `LicenseKeyGenerator::verify()` passa a retornar false.

Refund chargeback no MockPaymentGateway só passa para purchases `completed` — estados `pending` ou `failed` retornam `false`.

## Payouts

Schema implementado: tabela `arqel_plugin_payouts` com `plugin_id`, `publisher_user_id`, `amount_cents`, `currency`, `status` (`pending|paid|failed`), `period_start`, `period_end`.

### Como funciona (futuro)

O cron job mensal (TODO `arqel:marketplace:payouts` follow-up) executa:

1. Para cada `publisher_user_id` ativo, calcula sum de `purchases.amount_cents * (revenue_share_percent / 100)` no período (mês corrente menos período de hold de 14 dias para refunds).
2. Se sum ≥ threshold (`$50` USD ou equivalente em outras currencies), cria row `arqel_plugin_payouts` com `status=pending`.
3. Dispatch job que chama Stripe Connect transfer (ou equivalente).
4. Se transfer succeed → `status=paid`. Se falhar → `status=failed`, retry no próximo mês.

### Threshold mínimo

Pagamentos abaixo de **$50 USD** (ou equivalente) acumulam para o próximo período. Isso evita fees de transfer comerem o payout.

### Listagem para publishers

```http
GET /api/marketplace/publisher/payouts?per_page=20
Authorization: Bearer <publisher_token>
```

Retorna paginado, filtrado por `publisher_user_id = auth()->id()`. Cada row inclui `period_start`, `period_end`, `amount_cents`, `currency`, `status`.

## Revenue share

Default: **80% publisher / 20% Arqel**.

A Arqel cobra 20% para cobrir:

- Hosting do marketplace + CDN para downloads.
- Security scanning (`SecurityScanner` + integração futura com GitHub Advisory Database).
- Manual review por curadores humanos.
- Payment gateway fees (Stripe Connect cobra ~2.9% + $0.30 por transação; sobra para Arqel ~17%).
- Suporte ao customer (refund disputes, fraud detection).

### Customização

Por plugin, `revenue_share_percent` pode ser ajustado individualmente via admin (Gate `marketplace.update-revenue-share`). Casos típicos:

- **Founding publishers** (primeiros 50 que publicam premium): 90/10.
- **Plugins enterprise** com price_cents > $500: 85/15 (volume justifica desconto).
- **Plugins de charity** (publisher dona 100% para ONG): 100/0 com aprovação manual + due diligence.

## Tax & legal disclaimer

**Publishers são responsáveis** por:

- Declaração de receita ao fisco (BR: Receita Federal; EU: VAT MOSS; US: 1099-K se passar threshold).
- Issuing de invoices/notas fiscais para customers se exigido pela jurisdição.
- Compliance com termos de serviço do gateway (Stripe Connect ToS).

A Arqel **não** emite invoices em nome do publisher e **não** retém impostos automaticamente (exceto em jurisdições onde marketplace aggregator tax rules aplicam — ex: EU VAT em B2C, US states com marketplace facilitator laws).

Setup de Stripe Connect Express durante onboarding força o publisher a fornecer:

- Razão social ou CPF/CNPJ (BR), SSN/EIN (US), VAT ID (EU).
- Endereço fiscal.
- Conta bancária para payout.

Sem esses dados, plugins do publisher ficam restritos a `price_cents = 0` (free).

## Comparação rápida

| Cenário | Free plugin | Premium plugin |
|---|---|---|
| Submission | Igual | Exige Stripe Connect onboarding |
| `price_cents` | `0` | `>= 100` (mín. $1) |
| License key | Não emitida | Emitida em confirm |
| Download | Direto | Exige license válida |
| Reviews | Permite | Permite + flag `verified_purchaser` populada |
| Auto-delist em scan critical | Igual | Igual |
| Refund | N/A | Até 14 dias automático |
| Payout | N/A | Mensal, threshold $50 |

## Checklist do publisher premium

- [ ] Stripe Connect onboarding completo (TODO follow-up).
- [ ] `price_cents` definido (mín. $1 = `100`).
- [ ] `currency` correta para o seu mercado.
- [ ] `LICENSE.md` no repo se uso `Proprietary`.
- [ ] Refund policy documentada no README do plugin.
- [ ] Tax setup feito junto ao seu contador.
- [ ] CHANGELOG.md mantém versão semver consistente para license validation futura por version range.

## Próximos passos

- Submeter um plugin premium pela primeira vez? Reveja [Publicando plugins](./publishing.md) — o pipeline é idêntico ao free, com exceção do Stripe Connect onboarding.
- Plugin reprovado por security? [Boas práticas de segurança](./security-best-practices.md).
- Quer entender o backend payment? Veja seção MKTPLC-008 em `packages/marketplace/SKILL.md`.
