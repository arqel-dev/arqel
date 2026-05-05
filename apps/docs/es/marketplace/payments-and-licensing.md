# Pagos y licencias

> Cómo poner precio a plugins, generar license keys, procesar payouts y gestionar reembolsos en el Arqel Marketplace.

Esta página cubre el ciclo financiero completo de un plugin pago — desde `price_cents` en la fila de submission hasta el payout mensual al publisher. Documenta lo entregado (MKTPLC-008) y lo que aún es placeholder hasta el follow-up real de Stripe Connect.

## Estado actual

| Componente | Estado |
|---|---|
| Schema de purchase + license + payout | ✅ Entregado (MKTPLC-008) |
| `MockPaymentGateway` (default para dev/test) | ✅ Entregado |
| Formato `LicenseKeyGenerator` `ARQ-XXXX-XXXX-XXXX-XXXX` | ✅ Entregado |
| Endpoints `purchase` / `confirm` / `download` / `refund` | ✅ Entregados |
| `StripeConnectGateway` real | ✅ Entregado — opt-in vía `composer require stripe/stripe-php` + config `payment_gateway=stripe` |
| Webhooks Stripe (idempotencia, refund.updated) | ⏳ Próximo follow-up |
| Cron job de payouts (mensual, threshold de $50) | ⏳ Schema listo, scheduler es TODO follow-up |
| Facturación fiscal | ⏳ Fuera del scope de Arqel — el publisher es responsable |

Por defecto el paquete bindea `PaymentGateway => MockPaymentGateway`. Las apps host (incluido `arqel.dev/marketplace` en producción) rebindean a un gateway real cuando esté disponible:

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

El `StripeConnectGateway` real es opt-in — las apps que quieran cobrar por plugins reales activan el gateway vía una dependencia extra + config. El SDK `stripe/stripe-php` añade ~200KB al vendor, por eso el paquete `arqel-dev/marketplace` lo declara bajo `suggest` (no `require`). Las apps que solo consumen plugins gratuitos no pagan el costo.

### Prerrequisitos

1. **Cuenta Stripe activa** con Connect habilitado. Visita [dashboard.stripe.com/connect](https://dashboard.stripe.com/connect/overview) y sigue el onboarding de plataforma. Cobertura: USD, BRL, EUR, GBP y 130+ monedas más.
2. **Plan Connect** — Standard, Express o Custom. Para el marketplace estándar de Arqel, recomendamos **Express** (mínima fricción de onboarding para publishers; Stripe hostea el KYC).
3. **Endpoint webhook público** — Stripe entrega eventos sobre HTTPS. En dev, usa la [Stripe CLI](https://stripe.com/docs/stripe-cli) (`stripe listen --forward-to localhost:8000/stripe/webhook`).

### Instalación

```bash
composer require stripe/stripe-php
```

Verifica la instalación:

```bash
composer show stripe/stripe-php
# stripe/stripe-php  v16.x.x  Stripe PHP Library
```

### Config + variables de entorno

En el `.env` de la app:

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

`MarketplaceServiceProvider` lee estos valores en `register()` e instancia `StripeConnectGateway` automáticamente. Si `ARQEL_MARKETPLACE_PAYMENT_GATEWAY=stripe` pero el SDK no está instalado, el provider hace fallback a `MockPaymentGateway` y loguea un warning — así la app no se rompe en CI/dev sin el SDK.

### Onboarding de publishers (Connect)

Cada publisher que quiera vender plugins necesita una **cuenta Connect** vinculada a tu cuenta de plataforma. El flujo recomendado es Express (hosteado por Stripe):

1. El publisher hace click en "Set up payouts" en el admin Arqel.
2. La app crea una cuenta Connect Express a través de la API Stripe:

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

3. La app crea un account link y redirige al publisher al onboarding hosteado por Stripe:

   ```php
   $link = $stripe->accountLinks->create([
       'account' => $account->id,
       'refresh_url' => 'https://arqel.dev/publisher/stripe/refresh',
       'return_url' => 'https://arqel.dev/publisher/stripe/return',
       'type' => 'account_onboarding',
   ]);

   return redirect($link->url);
   ```

4. Tras el onboarding, persiste `account->id` en `arqel_plugins.publisher_stripe_account_id` (columna añadida por la migración `2026_05_07_000000_add_publisher_stripe_to_arqel_plugins.php`). De ahí en adelante, cada checkout de plugin dispatcha `application_fee_amount` (corte de Arqel, default 20%) + `transfer_data.destination` (cuenta del publisher).

> Plugins **sin** un `publisher_stripe_account_id` poblado siguen funcionando — todo el pago queda en la plataforma. Útil para los plugins propios de Arqel o mientras el publisher no haya terminado el onboarding.

### Probando con tarjetas de test

En dev/staging usa `STRIPE_SECRET=sk_test_...`. Stripe ofrece tarjetas de test predecibles para simular escenarios:

| Número | Resultado |
|---|---|
| `4242 4242 4242 4242` | Éxito |
| `4000 0000 0000 0002` | Decline genérico |
| `4000 0025 0000 3155` | Autenticación requerida (3D Secure) |
| `4000 0000 0000 9995` | Fondos insuficientes |

Usa cualquier CVC de 3 dígitos, cualquier ZIP de 5 dígitos, cualquier fecha futura. Lista completa en [stripe.com/docs/testing](https://stripe.com/docs/testing#cards).

Flujo end-to-end en dev:

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

### Troubleshooting común

| Síntoma | Causa | Fix |
|---|---|---|
| `RuntimeException: stripe/stripe-php SDK not installed` | SDK no instalado pero gateway activado | `composer require stripe/stripe-php` |
| El bind del provider hace fallback a Mock + log warning | `payment_gateway=stripe` pero `class_exists(StripeClient)` false | Igual al anterior — revisa `composer show stripe/stripe-php` |
| `MarketplaceException: Failed to create Stripe checkout session` | Error upstream de Stripe (auth, currency inválida, etc.) | Mira `storage/logs/laravel.log` — la excepción original va adjunta como `previous` |
| El checkout no redirige a la cuenta del publisher | `publisher_stripe_account_id` null en el plugin | Completa el onboarding Connect; persiste la columna |
| El application fee se ve mal | `STRIPE_PLATFORM_FEE_PERCENT` no coincide con la expectativa | El cast es `(int)`; el valor es porcentaje de `price_cents`. Para 15%, pon `STRIPE_PLATFORM_FEE_PERCENT=15` |

## Pricing del plugin

Cada fila en `arqel_plugins` lleva cuatro columnas relevantes para pricing:

| Columna | Tipo | Default | Significado |
|---|---|---|---|
| `price_cents` | int | `0` | Precio en céntimos de `currency`. `0` = gratis. |
| `currency` | string(3) | `USD` | ISO 4217. Usa `EUR`, `BRL`, `GBP`, etc. para precios localizados. |
| `publisher_user_id` | int nullable | poblado en submission | FK a `users` — destinatario del payout. |
| `revenue_share_percent` | int | `80` | % de `price_cents` que va al publisher; el resto (`100 - revenue_share_percent`) se queda con Arqel. |

El accessor `Plugin::isPremium()` devuelve `price_cents > 0`. Ese boolean dispara el pipeline de checkout en lugar de una descarga directa.

### Cómo poner precio

Recomendaciones para definir `price_cents`:

| Rango | Tipo de plugin | Ejemplo |
|---|---|---|
| Gratis (`0`) | Helpers genéricos, fields básicos, OSS puro | `arqel-dev/fields-markdown` |
| `$5-$15` | Field-pack con integración a SDK de terceros | Stripe Card, Mapbox Address |
| `$20-$50` | Widget-pack o integración crítica (Slack, Sentry) | Widget de dashboards Sentry |
| `$100+` | Temas white-label o plugins enterprise (auth custom, multi-tenant SSO) | Temas corporativos |

El pricing por suscripción (mensual/anual) **no está implementado** en MKTPLC-008 — solo compra única. El modelo de suscripción aterriza en un follow-up futuro.

### Actualizaciones de precio

El precio puede actualizarse vía el endpoint admin del publisher (futuro `PATCH /publisher/plugins/{slug}` con Gate `marketplace.update`). Los aumentos de precio **no son retroactivos** — las compras existentes mantienen acceso permanente. Las reducciones de precio tampoco generan reembolsos automáticos.

## License keys

Cada compra completada genera una license key única en el formato:

```
ARQ-XXXX-XXXX-XXXX-XXXX
```

Donde cada `XXXX` es un grupo hex de 4 chars. Total: 16 chars hex = 64 bits de entropía (generado vía `random_bytes(8)` en `LicenseKeyGenerator::generate()`).

### Verificación

```php
use Arqel\Marketplace\Services\LicenseKeyGenerator;

$generator = app(LicenseKeyGenerator::class);

if ($generator->verify($licenseKey, $plugin)) {
    // license válida + status completed
}
```

`verify()` ejecuta tres checks:

1. Regex de formato `/^ARQ-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}$/`.
2. Match contra `arqel_plugin_purchases.license_key` vía `hash_equals` (timing-safe).
3. `status === 'completed'` (refunded o pending bloquean).

### Distribución

La license key se devuelve **una sola vez** en el payload de `confirm`:

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

El cliente del marketplace (CLI `arqel:install`) guarda la key en `~/.arqel/credentials.json` (TODO de la entrega `arqel-dev/cli`). Para volver a obtener la key, golpea `GET /publisher/purchases/{id}` (autenticado).

## Flujo completo de purchase

### 1. Iniciar

Usuario autenticado inicia una compra:

```http
POST /api/marketplace/plugins/acme-stripe-card/purchase
Authorization: Bearer <user_token>
```

Resultados posibles:

- **422** si el plugin es gratis (`price_cents === 0`).
- **401** si no autenticado.
- **404** si el plugin no está publicado.
- **200 con `already_owned: true`** si el usuario ya tiene una compra completada.
- **200 con `purchase + checkout`** en otros casos:

```json
{
  "purchase": { "id": 42, "status": "pending" },
  "checkout": {
    "url": "https://arqel.dev/marketplace/mock-checkout/acme-stripe-card",
    "session_id": "mock_abc123"
  }
}
```

El `checkout.url` redirige al usuario al gateway. En `MockPaymentGateway`, la URL es un stub que simplemente devuelve el `session_id`. En producción con Stripe Connect será una URL real de Stripe Checkout.

### 2. Confirmar (callback del gateway)

Tras pago exitoso, el gateway llama (o el frontend llama con el session id devuelto):

```http
POST /api/marketplace/plugins/acme-stripe-card/purchase/confirm
{ "paymentId": "mock_abc123" }
```

`PluginPurchaseController::confirm`:

1. Verifica vía `PaymentGateway::verifyPayment($paymentId)` — devuelve `PaymentResult` (`success`, `amount`, `currency`).
2. En éxito, marca la compra como `completed`, persiste `payment_id`, genera license key vía `LicenseKeyGenerator`.
3. Idempotente al re-confirmar — si la compra ya está `completed`, devuelve la misma license key.

### 3. Descarga

Con una compra completada, el usuario puede descargar:

```http
GET /api/marketplace/plugins/acme-stripe-card/download
Authorization: Bearer <user_token>
```

Plugins gratuitos liberan la descarga sin checks; los premium requieren una compra completada. Sin compra válida → **403 Forbidden**.

Respuesta:

```json
{
  "download_url": "https://arqel.dev/marketplace/download/acme-stripe-card/latest.zip",
  "license_key": "ARQ-A1B2-C3D4-E5F6-7890"
}
```

## Reembolsos

Política Arqel: **reembolso dentro de 14 días** desde la compra, sin preguntas. (protección al consumidor alineada con la UE + el Código de Defesa do Consumidor brasileño).

Pasados los 14 días, los reembolsos se evalúan caso a caso por el admin vía Gate `marketplace.refund`:

```http
POST /api/marketplace/admin/plugins/{slug}/refund/{purchaseId}
Authorization: Bearer <admin_token>
```

`AdminRefundController`:

1. Valida que la compra esté `completed` (422 si ya está reembolsada o pending).
2. Llama `PaymentGateway::processRefund($purchase->payment_id, $purchase->amount_cents)`.
3. Marca la compra como `refunded` + puebla `refunded_at`.
4. La license key queda invalidada — `LicenseKeyGenerator::verify()` ahora devuelve false.

Los chargebacks de reembolso en `MockPaymentGateway` solo tienen éxito para compras `completed` — los estados `pending` o `failed` devuelven `false`.

## Payouts

Schema entregado: la tabla `arqel_plugin_payouts` con `plugin_id`, `publisher_user_id`, `amount_cents`, `currency`, `status` (`pending|paid|failed`), `period_start`, `period_end`.

### Cómo funciona (futuro)

El cron job mensual (TODO follow-up `arqel:marketplace:payouts`) corre:

1. Para cada `publisher_user_id` activo, calcula la suma de `purchases.amount_cents * (revenue_share_percent / 100)` para el periodo (mes actual menos una ventana de hold de 14 días para reembolso).
2. Si la suma ≥ threshold (`$50` USD o equivalente en otras monedas), crea una fila `arqel_plugin_payouts` con `status=pending`.
3. Dispatcha un job que llama a Stripe Connect transfer (o equivalente).
4. Si el transfer tiene éxito → `status=paid`. Si falla → `status=failed`, reintentado el mes siguiente.

### Threshold mínimo

Pagos por debajo de **$50 USD** (o equivalente) acumulan al siguiente periodo. Esto evita que las fees del transfer se coman el payout.

### Listing para publishers

```http
GET /api/marketplace/publisher/payouts?per_page=20
Authorization: Bearer <publisher_token>
```

Devuelve paginado, filtrado por `publisher_user_id = auth()->id()`. Cada fila incluye `period_start`, `period_end`, `amount_cents`, `currency`, `status`.

## Revenue share

Default: **80% publisher / 20% Arqel**.

Arqel toma el 20% para cubrir:

- Hosting del marketplace + CDN para descargas.
- Escaneo de seguridad (`SecurityScanner` + futura integración con la GitHub Advisory Database).
- Revisión manual por curadores humanos.
- Fees del payment gateway (Stripe Connect cobra ~2.9% + $0.30 por transacción; ~17% queda para Arqel).
- Soporte al cliente (disputas de reembolso, detección de fraude).

### Customización

Por plugin, `revenue_share_percent` puede ajustarse individualmente vía admin (Gate `marketplace.update-revenue-share`). Casos típicos:

- **Founding publishers** (los primeros 50 en publicar premium): 90/10.
- **Plugins enterprise** con price_cents > $500: 85/15 (el volumen justifica el descuento).
- **Plugins solidarios** (el publisher dona el 100% a una ONG): 100/0 con aprobación manual + due diligence.

## Disclaimer fiscal y legal

**Los publishers son responsables** de:

- Reportar los ingresos a las autoridades fiscales (BR: Receita Federal; UE: VAT MOSS; US: 1099-K si cruzas el threshold).
- Emitir facturas/notas fiscais a los clientes cuando lo exija la jurisdicción.
- Cumplimiento con los términos de servicio del gateway (Stripe Connect ToS).

Arqel **no** emite facturas en nombre del publisher y **no** retiene impuestos automáticamente (excepto en jurisdicciones donde aplican reglas de tax aggregator de marketplace — ej., VAT UE para B2C, estados US con leyes de marketplace facilitator).

El setup Stripe Connect Express durante el onboarding fuerza al publisher a proporcionar:

- Razón social o CPF/CNPJ (BR), SSN/EIN (US), VAT ID (UE).
- Dirección fiscal.
- Cuenta bancaria para payout.

Sin estos datos, los plugins del publisher quedan restringidos a `price_cents = 0` (gratis).

## Comparación rápida

| Escenario | Plugin gratis | Plugin premium |
|---|---|---|
| Submission | Igual | Requiere onboarding Stripe Connect |
| `price_cents` | `0` | `>= 100` (mín. $1) |
| License key | No emitida | Emitida en confirm |
| Descarga | Directa | Requiere licencia válida |
| Reviews | Permitidas | Permitidas + flag `verified_purchaser` poblado |
| Auto-delist en scan crítico | Igual | Igual |
| Reembolso | N/A | Dentro de 14 días, automático |
| Payout | N/A | Mensual, threshold de $50 |

## Checklist de publisher premium

- [ ] Onboarding Stripe Connect completo (TODO follow-up).
- [ ] `price_cents` definido (mín. $1 = `100`).
- [ ] `currency` correcta para tu mercado.
- [ ] `LICENSE.md` en el repo si usas `Proprietary`.
- [ ] Política de reembolso documentada en el README del plugin.
- [ ] Setup fiscal hecho con tu contador.
- [ ] CHANGELOG.md mantiene una versión semver consistente para futura validación de licencia por rango de versión.

## Próximos pasos

- ¿Submission de un plugin premium por primera vez? Revisa [Publicar plugins](./publishing.md) — el pipeline es idéntico al free, excepto por el onboarding Stripe Connect.
- ¿Plugin rechazado por seguridad? Mira [Buenas prácticas de seguridad](./security-best-practices.md).
- ¿Quieres entender el backend de pagos? Mira la sección MKTPLC-008 en `packages/marketplace/SKILL.md`.
