# Security best practices

> How to build and maintain Arqel plugins without becoming an attack vector for framework users.

Plugins **are code** that runs inside the user's admin with full Laravel application privileges — database, filesystem, env, sessions, queue. That is why the marketplace is security-first at every step: automatic scan before publish, license allow-list, auto-delist on `critical` findings, and an explicit disclosure path for reported vulnerabilities.

This page covers what you (the author) must avoid and what you (the consumer) should verify.

## Why security matters

An "innocent looking calendar widget" plugin installed in an admin that controls payroll inherits **every privilege** of the Laravel context:

- It can read `config/database.php` and open a PostgreSQL connection.
- It can issue arbitrary queries through `DB::raw`.
- It can read `storage/app/private/*` and tenant files.
- It can call `Mail::send` on behalf of the app.
- It can invoke Artisan commands if a request hits server-side.

The privilege barrier is **the user's trust at install time**. The marketplace adds defense-in-depth on top of that trust.

## Patterns to avoid (publishers)

### 1. Dynamic code evaluation with user input

**Vulnerable:** functions that execute strings (`exec`, `passthru`, `system`, `assert(string)`, `create_function`) applied to strings coming from the request — trivial RCE.

```php
$expression = $request->input('formula');
$result = run_dynamic("return {$expression};"); // exemplo conceitual; nunca faça isso
```

**Mitigation:** never evaluate dynamic strings. Use explicit parsers (e.g., `mathieuviossat/whatcoulditbe`) or a DSL with a whitelist.

`SecurityScanner` (future static analysis in MKTPLC-009-static-analysis) will automatically flag these functions as `critical` findings.

### 2. `file_get_contents` on URLs without an allowlist

**Vulnerable:**

```php
$image = file_get_contents($request->input('avatar_url'));
```

Allows SSRF — the attacker forces the server to hit `http://169.254.169.254/...` (AWS metadata) or VPC-internal services.

**Mitigation:**

```php
use Illuminate\Support\Facades\Http;

$allowedHosts = ['cdn.cloudflare.com', 'images.unsplash.com'];
$host = parse_url($url, PHP_URL_HOST);

if (! in_array($host, $allowedHosts, true)) {
    abort(422, 'URL host not allowed.');
}

$response = Http::timeout(5)->withoutRedirecting()->get($url);
```

### 3. SQL injection via raw queries

**Vulnerable:**

```php
DB::select("SELECT * FROM posts WHERE title LIKE '%{$search}%'");
```

**Mitigation:** always use bindings:

```php
DB::select('SELECT * FROM posts WHERE title LIKE ?', ["%{$search}%"]);
// ou query builder
DB::table('posts')->where('title', 'like', "%{$search}%")->get();
```

### 4. XSS via unsanitized HTML

**Vulnerable:** a React field that renders raw HTML through an unsafe prop (e.g., `dangerouslySetInnerHTML` or equivalents) with user input.

**Mitigation:** sanitize with `DOMPurify.sanitize(props.value)` or render as text. For markdown, use a parser that produces an AST + sanitizes during render (e.g., `react-markdown` with `rehype-sanitize`).

### 5. CSRF bypass on plugin routes

**Vulnerable:** registering a POST route without a `web` or `api` middleware:

```php
Route::post('/plugin/webhook', [WebhookController::class, 'handle']);
// sem CSRF, sem rate limit, sem auth
```

**Mitigation:** always inside `Route::middleware(['web', 'auth'])->group(...)` or `Route::middleware('api')->...`. For legitimate external webhooks, use signature verification (HMAC) + add to `VerifyCsrfToken::$except` with a clearly scoped path.

### 6. Mass assignment

**Vulnerable:**

```php
PluginSetting::create($request->all()); // user pode setar `is_admin: true`
```

**Mitigation:** always set `$fillable` or `$guarded` on the model + use a FormRequest with explicit validation rules:

```php
PluginSetting::create($request->validated());
```

### 7. Abandonware dependencies

A plugin that depends on a package with no release for 3+ years is a supply-chain attack vector — an attacker can take over maintenance and inject a payload in the next release.

**Mitigation:**

- Audit dependencies before publishing (`composer outdated`, `npm audit`).
- Prefer packages maintained by reputable orgs or authors.
- For critical dependencies, vendor the code (commit it into the repo) or fork into your own namespace.
- Use `composer require-checker` to detect orphaned deps.

### 8. Secrets in logs and error messages

**Vulnerable:**

```php
Log::error("Stripe API failed", ['key' => $apiKey]); // vaza key no log
throw new RuntimeException("Auth failed for token {$token}"); // vaza no Sentry
```

**Mitigation:**

```php
Log::error("Stripe API failed", ['key_suffix' => substr($apiKey, -4)]);
throw new RuntimeException('Auth failed for token: '.Str::mask($token, '*', 4));
```

Laravel ships `Str::mask()` since 9.x. For Sentry, configure a `before_send` callback for scrubbing.

### 9. SSRF via webhooks or OAuth callback

Same pattern as `file_get_contents` but on a controller route. Always validate the host of the redirect_uri/webhook before opening a connection.

### 10. Outdated polyfills

Front-end plugins importing polyfills from `core-js@2` or `babel-polyfill` drag in vulnerable code. Modernize to `core-js@3` or drop polyfills (modern browsers cover ES2022).

## License obligations

`SecurityScanner` checks `composer.json#license` against the allow-list:

| License | Marketplace status | Implication |
|---|---|---|
| `MIT` | recommended default | No strings attached, maximum compatibility |
| `Apache-2.0` | accepted | Patent clause + attribution |
| `BSD-2-Clause` / `BSD-3-Clause` | accepted | Like MIT, with extra redistribution clauses |
| `GPL-3.0`, `AGPL-3.0` | `low` warning | Viral copyleft — may contaminate the consuming app |
| `Proprietary` | `low` warning + mandatory `LICENSE.md` in repo | Accepted, but the user sees a yellow badge |
| `WTFPL`, `Unlicense` | `low` warning | Technically accepted but to be avoided |
| No license declared | fail | Without `composer.json#license`, the plugin is considered all-rights-reserved and the marketplace blocks it |

Premium plugins (`price_cents > 0`) may use `Proprietary` with no warning — that is the expected path when you charge.

## Sensitive data handling

Plugins handling sensitive data must follow three rules:

### Never log API keys or tokens

Use `Str::mask()` or explicit redaction. Configure `LOG_CHANNEL` to avoid storing logs in a public filesystem (Laravel already does not, but a plugin can override).

### Encrypt at rest

If your plugin stores tokens (OAuth refresh tokens, third-party API keys), use `Crypt::encryptString()`:

```php
$user->plugin_settings->update([
    'stripe_secret' => Crypt::encryptString($request->input('stripe_secret')),
]);
```

And always access through the `'encrypted'` cast on the model:

```php
protected function casts(): array
{
    return [
        'stripe_secret' => 'encrypted',
    ];
}
```

### Audit access patterns

For fields displaying PII, use `arqel-dev/audit` (core package) and dispatch an event when someone opens a record. The marketplace does not require audit, but health/financial plugins should ship it.

## Vulnerability disclosure

If you (the consumer) discover a vulnerability in a published plugin:

1. **Do not open a public issue on the plugin's GitHub** — that becomes a 0-day.
2. Email `security@arqel.dev` with:
   - Plugin slug.
   - Affected version.
   - Minimal PoC.
   - Impact description.
3. The Arqel team triages within 48h and contacts the author with a fix SLA:
   - `critical` → 7 days to patch or permanent auto-delist.
   - `high` → 14 days.
   - `medium`/`low` → 30 days.
4. After patching, a CVE is registered (if applicable) and public disclosure happens 90 days after the report.

Plugins with `critical` findings receive immediate auto-delist (`status=archived`) via `PluginAutoDelistedEvent` — installed users keep working, but the plugin no longer appears in fresh listings. When the author publishes a fix, they can resubmit via `POST /admin/plugins/{slug}/review` with `action=approve` (after a fresh scan).

## Plugin-design anti-patterns

Beyond classic vulnerabilities, avoid the following design choices that reduce trust:

### Opaque telemetry

A plugin that calls `Http::post('https://my-tracker.com/...')` in the background without documenting it and without opt-out **will be rejected**. Telemetry is accepted but must:

- Be documented in the README.
- Be opt-in (default off).
- Anonymize data (never send user emails, IDs, tokens).

### Aggressive auto-update

A plugin must not call `composer update` or modify `composer.json` at runtime. Updates are the user's responsibility via `arqel:install --update` or `composer update`.

### Administrative backdoors

A plugin must not create an admin user, generate an API token, or modify `users.is_admin` without explicit user action. Even when "convenient" for support.

### Core-package modification

A plugin must not rewrite an `arqel-dev/core` class through service container overrides without documenting it. If you need to extend behavior, use the official events or contracts.

## Hardening checklist

Before submitting, confirm:

- [ ] No dynamic-evaluation calls (`exec`, `passthru`, `system`, `assert(string)`) in production code.
- [ ] No `file_get_contents($userInput)` or `curl` against user-supplied URLs.
- [ ] All queries via Eloquent or the builder with bindings.
- [ ] React render does not inject raw HTML with unsanitized user input.
- [ ] POST/PUT/DELETE routes under appropriate `auth` middleware.
- [ ] Models with explicit `$fillable` or `$guarded`.
- [ ] `composer audit` + `pnpm audit` clean of critical/high issues.
- [ ] Secrets never appear in logs or exception messages.
- [ ] License declared and on the allow-list.
- [ ] CHANGELOG.md mentions security fixes (when applicable).

## Next steps

- Already fixed a reported vulnerability? Resubmit following [Publishing plugins](./publishing.md).
- Want to understand the full scan pipeline? See section MKTPLC-009 in `packages/marketplace/SKILL.md`.
- Reporting a vulnerability in the Arqel framework itself (not in a plugin)? Use `SECURITY.md` in the main repository.
