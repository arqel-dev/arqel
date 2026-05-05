# Buenas prácticas de seguridad

> Cómo construir y mantener plugins Arqel sin convertirte en un vector de ataque para los usuarios del framework.

Los plugins **son código** que se ejecuta dentro del admin del usuario con todos los privilegios de la aplicación Laravel — base de datos, filesystem, env, sessions, queue. Por eso el marketplace es security-first en cada paso: escaneo automático antes de publicar, license allow-list, auto-delist ante hallazgos `critical` y un camino de disclosure explícito para vulnerabilidades reportadas.

Esta página cubre lo que tú (autor) debes evitar y lo que tú (consumidor) debes verificar.

## Por qué importa la seguridad

Un plugin "calendario inocente" instalado en un admin que controla nómina hereda **todos los privilegios** del contexto Laravel:

- Puede leer `config/database.php` y abrir una conexión PostgreSQL.
- Puede emitir queries arbitrarias a través de `DB::raw`.
- Puede leer `storage/app/private/*` y archivos de tenants.
- Puede llamar a `Mail::send` en nombre de la app.
- Puede invocar comandos Artisan si una request llega al server-side.

La barrera de privilegio es **la confianza del usuario al instalar**. El marketplace añade defensa en profundidad sobre esa confianza.

## Patrones a evitar (publishers)

### 1. Evaluación dinámica de código con input del usuario

**Vulnerable:** funciones que ejecutan strings (`exec`, `passthru`, `system`, `assert(string)`, `create_function`) aplicadas a strings provenientes de la request — RCE trivial.

```php
$expression = $request->input('formula');
$result = run_dynamic("return {$expression};"); // exemplo conceitual; nunca faça isso
```

**Mitigación:** nunca evalúes strings dinámicos. Usa parsers explícitos (ej., `mathieuviossat/whatcoulditbe`) o un DSL con whitelist.

`SecurityScanner` (futuro análisis estático en MKTPLC-009-static-analysis) marcará automáticamente estas funciones como hallazgos `critical`.

### 2. `file_get_contents` en URLs sin allowlist

**Vulnerable:**

```php
$image = file_get_contents($request->input('avatar_url'));
```

Permite SSRF — el atacante fuerza al servidor a golpear `http://169.254.169.254/...` (AWS metadata) o servicios internos de la VPC.

**Mitigación:**

```php
use Illuminate\Support\Facades\Http;

$allowedHosts = ['cdn.cloudflare.com', 'images.unsplash.com'];
$host = parse_url($url, PHP_URL_HOST);

if (! in_array($host, $allowedHosts, true)) {
    abort(422, 'URL host not allowed.');
}

$response = Http::timeout(5)->withoutRedirecting()->get($url);
```

### 3. SQL injection vía raw queries

**Vulnerable:**

```php
DB::select("SELECT * FROM posts WHERE title LIKE '%{$search}%'");
```

**Mitigación:** usa siempre bindings:

```php
DB::select('SELECT * FROM posts WHERE title LIKE ?', ["%{$search}%"]);
// ou query builder
DB::table('posts')->where('title', 'like', "%{$search}%")->get();
```

### 4. XSS vía HTML sin sanitizar

**Vulnerable:** un field React que renderiza HTML crudo a través de un prop inseguro (ej., `dangerouslySetInnerHTML` o equivalentes) con input del usuario.

**Mitigación:** sanitiza con `DOMPurify.sanitize(props.value)` o renderiza como texto. Para markdown, usa un parser que produzca un AST + sanitiza durante el render (ej., `react-markdown` con `rehype-sanitize`).

### 5. Bypass de CSRF en rutas del plugin

**Vulnerable:** registrar una ruta POST sin un middleware `web` o `api`:

```php
Route::post('/plugin/webhook', [WebhookController::class, 'handle']);
// sem CSRF, sem rate limit, sem auth
```

**Mitigación:** siempre dentro de `Route::middleware(['web', 'auth'])->group(...)` o `Route::middleware('api')->...`. Para webhooks externos legítimos, usa verificación de firma (HMAC) + añade a `VerifyCsrfToken::$except` con un path claramente acotado.

### 6. Mass assignment

**Vulnerable:**

```php
PluginSetting::create($request->all()); // user pode setar `is_admin: true`
```

**Mitigación:** define siempre `$fillable` o `$guarded` en el model + usa un FormRequest con reglas de validación explícitas:

```php
PluginSetting::create($request->validated());
```

### 7. Dependencias abandonware

Un plugin que depende de un paquete sin release durante 3+ años es un vector de ataque de supply-chain — un atacante puede tomar el mantenimiento e inyectar un payload en el siguiente release.

**Mitigación:**

- Audita dependencias antes de publicar (`composer outdated`, `npm audit`).
- Prefiere paquetes mantenidos por orgs o autores reputados.
- Para dependencias críticas, vendora el código (commitéalo al repo) o haz fork a tu propio namespace.
- Usa `composer require-checker` para detectar deps huérfanas.

### 8. Secretos en logs y mensajes de error

**Vulnerable:**

```php
Log::error("Stripe API failed", ['key' => $apiKey]); // vaza key no log
throw new RuntimeException("Auth failed for token {$token}"); // vaza no Sentry
```

**Mitigación:**

```php
Log::error("Stripe API failed", ['key_suffix' => substr($apiKey, -4)]);
throw new RuntimeException('Auth failed for token: '.Str::mask($token, '*', 4));
```

Laravel incluye `Str::mask()` desde 9.x. Para Sentry, configura un callback `before_send` para scrubbing.

### 9. SSRF vía webhooks o callback OAuth

Mismo patrón que `file_get_contents` pero en una ruta de controller. Valida siempre el host del redirect_uri/webhook antes de abrir una conexión.

### 10. Polyfills desactualizados

Plugins front-end importando polyfills desde `core-js@2` o `babel-polyfill` arrastran código vulnerable. Moderniza a `core-js@3` o elimina los polyfills (los browsers modernos cubren ES2022).

## Obligaciones de licencia

`SecurityScanner` chequea `composer.json#license` contra el allow-list:

| Licencia | Estado en marketplace | Implicación |
|---|---|---|
| `MIT` | default recomendado | Sin condiciones, máxima compatibilidad |
| `Apache-2.0` | aceptada | Cláusula de patentes + atribución |
| `BSD-2-Clause` / `BSD-3-Clause` | aceptada | Como MIT, con cláusulas extra de redistribución |
| `GPL-3.0`, `AGPL-3.0` | warning `low` | Copyleft viral — puede contaminar la app consumidora |
| `Proprietary` | warning `low` + `LICENSE.md` obligatorio en repo | Aceptada, pero el usuario ve un badge amarillo |
| `WTFPL`, `Unlicense` | warning `low` | Técnicamente aceptadas pero a evitar |
| Sin licencia declarada | fail | Sin `composer.json#license`, el plugin se considera all-rights-reserved y el marketplace lo bloquea |

Plugins premium (`price_cents > 0`) pueden usar `Proprietary` sin warning — ese es el camino esperado cuando cobras.

## Manejo de datos sensibles

Los plugins que manejan datos sensibles deben seguir tres reglas:

### Nunca loguear API keys o tokens

Usa `Str::mask()` o redacción explícita. Configura `LOG_CHANNEL` para evitar guardar logs en un filesystem público (Laravel ya no lo hace, pero un plugin puede sobrescribir).

### Encriptar en reposo

Si tu plugin almacena tokens (refresh tokens OAuth, API keys de terceros), usa `Crypt::encryptString()`:

```php
$user->plugin_settings->update([
    'stripe_secret' => Crypt::encryptString($request->input('stripe_secret')),
]);
```

Y accede siempre a través del cast `'encrypted'` en el model:

```php
protected function casts(): array
{
    return [
        'stripe_secret' => 'encrypted',
    ];
}
```

### Auditar patrones de acceso

Para fields que muestren PII, usa `arqel-dev/audit` (paquete core) y dispatcha un evento cuando alguien abra un record. El marketplace no requiere audit, pero los plugins de salud/financieros deberían incluirlo.

## Disclosure de vulnerabilidades

Si tú (consumidor) descubres una vulnerabilidad en un plugin publicado:

1. **No abras un issue público en el GitHub del plugin** — eso se convierte en un 0-day.
2. Envía email a `security@arqel.dev` con:
   - Slug del plugin.
   - Versión afectada.
   - PoC mínimo.
   - Descripción del impacto.
3. El equipo Arqel hace triage en 48h y contacta al autor con un SLA de fix:
   - `critical` → 7 días para parchear o auto-delist permanente.
   - `high` → 14 días.
   - `medium`/`low` → 30 días.
4. Tras el parche, se registra un CVE (si aplica) y la disclosure pública ocurre 90 días después del reporte.

Plugins con hallazgos `critical` reciben auto-delist inmediato (`status=archived`) vía `PluginAutoDelistedEvent` — los usuarios instalados siguen funcionando, pero el plugin ya no aparece en listados nuevos. Cuando el autor publique un fix, puede re-enviar vía `POST /admin/plugins/{slug}/review` con `action=approve` (tras un nuevo scan).

## Anti-patrones de diseño de plugin

Más allá de las vulnerabilidades clásicas, evita las siguientes decisiones de diseño que reducen confianza:

### Telemetría opaca

Un plugin que llama `Http::post('https://my-tracker.com/...')` en background sin documentarlo y sin opt-out **será rechazado**. La telemetría se acepta pero debe:

- Estar documentada en el README.
- Ser opt-in (default off).
- Anonimizar datos (nunca enviar emails, IDs, tokens del usuario).

### Auto-update agresivo

Un plugin no debe llamar `composer update` ni modificar `composer.json` en runtime. Las actualizaciones son responsabilidad del usuario vía `arqel:install --update` o `composer update`.

### Backdoors administrativos

Un plugin no debe crear un usuario admin, generar un API token o modificar `users.is_admin` sin acción explícita del usuario. Aunque sea "conveniente" para soporte.

### Modificación de paquetes core

Un plugin no debe reescribir una clase de `arqel-dev/core` mediante overrides del service container sin documentarlo. Si necesitas extender comportamiento, usa los eventos o contracts oficiales.

## Checklist de hardening

Antes de hacer submission, confirma:

- [ ] Sin llamadas a evaluación dinámica (`exec`, `passthru`, `system`, `assert(string)`) en código de producción.
- [ ] Sin `file_get_contents($userInput)` ni `curl` contra URLs proporcionadas por el usuario.
- [ ] Todas las queries vía Eloquent o el builder con bindings.
- [ ] El render React no inyecta HTML crudo con input del usuario sin sanitizar.
- [ ] Rutas POST/PUT/DELETE bajo middleware `auth` apropiado.
- [ ] Models con `$fillable` o `$guarded` explícitos.
- [ ] `composer audit` + `pnpm audit` limpios de issues critical/high.
- [ ] Los secretos nunca aparecen en logs ni en mensajes de excepción.
- [ ] Licencia declarada y en el allow-list.
- [ ] CHANGELOG.md menciona security fixes (cuando aplica).

## Próximos pasos

- ¿Ya parcheaste una vulnerabilidad reportada? Re-envía siguiendo [Publicar plugins](./publishing.md).
- ¿Quieres entender el pipeline completo de scan? Mira la sección MKTPLC-009 en `packages/marketplace/SKILL.md`.
- ¿Reportando una vulnerabilidad en el framework Arqel mismo (no en un plugin)? Usa `SECURITY.md` en el repositorio principal.
