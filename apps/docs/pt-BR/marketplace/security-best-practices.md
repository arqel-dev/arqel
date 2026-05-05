# Boas práticas de segurança

> Como construir e manter plugins Arqel sem virar vetor de ataque para os usuários do framework.

Plugins **são código** que roda dentro do admin do usuário com privilégios de aplicação Laravel — acesso a banco, filesystem, env, sessões, queue. Por isso o marketplace adota security-first em todas as etapas: scan automático antes do publish, allow-list de licenças, auto-delist em finding `critical`, e disclosure path explícito para vulnerabilidades reportadas.

Esta página cobre o que você (autor) deve evitar e o que você (consumidor) deve verificar.

## Por que segurança importa

Um plugin de "innocent looking calendar widget" instalado em um admin que controla folha de pagamento herda **todos os privilégios** do contexto Laravel:

- Pode ler `config/database.php` e abrir conexão PostgreSQL.
- Pode emitir queries arbitrárias via `DB::raw`.
- Pode ler `storage/app/private/*` e arquivos do tenant.
- Pode chamar `Mail::send` em nome do app.
- Pode invocar Artisan commands se o request hit ServerSide.

A barreira de privilégio é **a confiança do usuário ao instalar**. O marketplace adiciona camadas de defesa profunda em cima dessa confiança.

## Patterns a evitar (publishers)

### 1. Avaliação dinâmica de código com user input

**Vulnerável:** funções que executam strings (`exec`, `passthru`, `system`, `assert(string)`, `create_function`) aplicadas em strings vindas do request — RCE trivial.

```php
$expression = $request->input('formula');
$result = run_dynamic("return {$expression};"); // exemplo conceitual; nunca faça isso
```

**Mitigação:** nunca avalie strings dinâmicas. Use parsers explícitos (e.g. `mathieuviossat/whatcoulditbe`) ou DSL com whitelist.

O `SecurityScanner` (futuro static analysis em MKTPLC-009-static-analysis) vai marcar essas funções automaticamente como finding `critical`.

### 2. `file_get_contents` em URLs sem allowlist

**Vulnerável:**

```php
$image = file_get_contents($request->input('avatar_url'));
```

Permite SSRF — atacante força o servidor a hitar `http://169.254.169.254/...` (AWS metadata) ou serviços internos da VPC.

**Mitigação:**

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

**Vulnerável:**

```php
DB::select("SELECT * FROM posts WHERE title LIKE '%{$search}%'");
```

**Mitigação:** sempre usar bindings:

```php
DB::select('SELECT * FROM posts WHERE title LIKE ?', ["%{$search}%"]);
// ou query builder
DB::table('posts')->where('title', 'like', "%{$search}%")->get();
```

### 4. XSS via HTML não sanitizado

**Vulnerável:** field React que renderiza HTML cru via prop unsafe (e.g., `dangerouslySetInnerHTML` ou equivalentes) com input do usuário.

**Mitigação:** sanitize com `DOMPurify.sanitize(props.value)` ou render como texto. Para markdown, use parser que gera AST + sanitiza no caminho de render (ex: `react-markdown` com `rehype-sanitize`).

### 5. CSRF bypass em routes do plugin

**Vulnerável:** registrar route POST sem middleware `web` ou `api` configurado:

```php
Route::post('/plugin/webhook', [WebhookController::class, 'handle']);
// sem CSRF, sem rate limit, sem auth
```

**Mitigação:** sempre dentro de `Route::middleware(['web', 'auth'])->group(...)` ou `Route::middleware('api')->...`. Para webhooks externos legítimos, use signature verification (HMAC) + adicione no `VerifyCsrfToken::$except` com escopo claro.

### 6. Mass assignment

**Vulnerável:**

```php
PluginSetting::create($request->all()); // user pode setar `is_admin: true`
```

**Mitigação:** sempre `$fillable` ou `$guarded` no model + use FormRequest com validation rules explícitas:

```php
PluginSetting::create($request->validated());
```

### 7. Dependências abandonware

Plugin que depende de pacote sem release há 3+ anos é vetor de supply chain attack — atacante pode tomar a manutenção e injetar payload na próxima release.

**Mitigação:**

- Audit dependências antes de publicar (`composer outdated`, `npm audit`).
- Prefira pacotes mantidos por orgs ou autores reputados.
- Para dependências críticas, vendor o código (commit no repo) ou fork em namespace próprio.
- Use `composer require-checker` para detectar deps órfãs.

### 8. Secrets em logs e error messages

**Vulnerável:**

```php
Log::error("Stripe API failed", ['key' => $apiKey]); // vaza key no log
throw new RuntimeException("Auth failed for token {$token}"); // vaza no Sentry
```

**Mitigação:**

```php
Log::error("Stripe API failed", ['key_suffix' => substr($apiKey, -4)]);
throw new RuntimeException('Auth failed for token: '.Str::mask($token, '*', 4));
```

Laravel oferece `Str::mask()` desde 9.x. Para Sentry, configure `before_send` callback para scrubbing.

### 9. SSRF via webhooks ou OAuth callback

Mesmo padrão que `file_get_contents` mas em controller route. Sempre valide o host do redirect_uri/webhook antes de abrir conexão.

### 10. Polyfills outdated

Plugins front-end que importam polyfills de `core-js@2` ou `babel-polyfill` arrastam código vulnerável. Modernize para `core-js@3` ou remova polyfills (browsers modernos cobrem ES2022).

## License obligations

O `SecurityScanner` checa `composer.json#license` contra allow-list:

| Licença | Status no marketplace | Implicação |
|---|---|---|
| `MIT` | default recomendado | Sem strings attached, compatibilidade máxima |
| `Apache-2.0` | aceito | Cláusula de patentes + atribuição |
| `BSD-2-Clause` / `BSD-3-Clause` | aceito | Como MIT, com cláusulas extras de redistribuição |
| `GPL-3.0`, `AGPL-3.0` | warning `low` | Copyleft viral — pode contaminar app que instala |
| `Proprietary` | warning `low` + obrigatório `LICENSE.md` no repo | Aceito mas usuário vê selo amarelo |
| `WTFPL`, `Unlicense` | warning `low` | Tecnicamente aceito mas evitar |
| Sem licença declarada | fail | Sem `composer.json#license`, o plugin é considerado all-rights-reserved e o marketplace bloqueia |

Premium plugins (`price_cents > 0`) podem usar `Proprietary` sem warning — é o caminho esperado quando você cobra.

## Sensitive data handling

Plugins que tocam dados sensíveis precisam seguir três regras:

### Nunca log API keys ou tokens

Use `Str::mask()` ou redação explícita. Configure `LOG_CHANNEL` para evitar arquivar logs em filesystem público (Laravel já não faz, mas plugin pode override).

### Encrypt at rest

Se seu plugin armazena tokens (OAuth refresh tokens, API keys de terceiros), use `Crypt::encryptString()`:

```php
$user->plugin_settings->update([
    'stripe_secret' => Crypt::encryptString($request->input('stripe_secret')),
]);
```

E sempre access via cast `'encrypted'` no model:

```php
protected function casts(): array
{
    return [
        'stripe_secret' => 'encrypted',
    ];
}
```

### Audit access patterns

Para fields que mostram dados PII, use `arqel-dev/audit` (pacote core) e dispare event quando alguém abre o registro. O marketplace não exige audit, mas plugins de saúde/financeiro deveriam.

## Vulnerability disclosure

Se você (consumidor) descobrir vulnerabilidade em um plugin published:

1. **Não abra issue público no GitHub do plugin** — vira 0-day.
2. Envie email para `security@arqel.dev` com:
   - Plugin slug.
   - Versão afetada.
   - PoC mínima.
   - Descrição do impacto.
3. A equipe Arqel triage em 48h e contata o autor com SLA de fix:
   - `critical` → 7 dias para patch ou auto-delist permanente.
   - `high` → 14 dias.
   - `medium`/`low` → 30 dias.
4. Após patch, CVE é registrada (se aplicável) e public disclosure acontece em 90 dias do report.

Plugins com finding `critical` recebem auto-delist imediato (`status=archived`) via `PluginAutoDelistedEvent` — usuários instalados continuam funcionando, mas o plugin não aparece mais em listings novos. Quando o autor pública fix, ele pode resubmeter via `POST /admin/plugins/{slug}/review` com `action=approve` (após scan novo).

## Anti-patterns no design do plugin

Além de vulnerabilidades clássicas, evite estas decisões de design que reduzem trust:

### Telemetria opaca

Plugin que faz `Http::post('https://my-tracker.com/...')` em background sem documentar isso e sem opt-out **será rejeitado**. Telemetria é aceita mas precisa:

- Ser documentada no README.
- Ser opt-in (default off).
- Anonymizar dados (nunca enviar user emails, IDs, tokens).

### Auto-update agressivo

Plugin não pode chamar `composer update` ou modificar `composer.json` em runtime. Atualizações são responsabilidade do user via `arqel:install --update` ou `composer update`.

### Backdoors administrativos

Plugin não pode criar usuário admin, gerar API token, ou modificar `users.is_admin` sem ação explícita do usuário do plugin. Mesmo quando "convenient" para suporte.

### Modificação de pacotes core

Plugin não pode reescrever class de `arqel-dev/core` via service container override sem documentar. Se precisa estender comportamento, use eventos ou contracts oficiais.

## Checklist de hardening

Antes de submeter, confira:

- [ ] Sem chamadas de avaliação dinâmica (`exec`, `passthru`, `system`, `assert(string)`) em código de production.
- [ ] Sem `file_get_contents($userInput)` ou `curl` em URLs do user.
- [ ] Todas queries via Eloquent ou builder com bindings.
- [ ] Render React não injeta HTML cru com user input não-sanitizado.
- [ ] Routes POST/PUT/DELETE sob middleware `auth` apropriado.
- [ ] Models com `$fillable` ou `$guarded` explícitos.
- [ ] Dependências `composer audit` + `pnpm audit` sem critical/high.
- [ ] Secrets nunca aparecem em log ou exception messages.
- [ ] License declarada e na allow-list.
- [ ] CHANGELOG.md menciona security fixes (se aplicável).

## Próximos passos

- Já corrigiu vulnerabilidade reportada? Re-submeta seguindo [Publicando plugins](./publishing.md).
- Quer entender o pipeline de scan completo? Veja seção MKTPLC-009 em `packages/marketplace/SKILL.md`.
- Reportar vulnerabilidade no framework Arqel (não em plugin)? Use `SECURITY.md` no repositório principal.
