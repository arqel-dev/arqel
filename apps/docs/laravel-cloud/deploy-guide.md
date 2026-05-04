# Deploy guide — Arqel no Laravel Cloud

Este guia leva você do **zero ao painel em produção** em menos de 10 minutos.
Se algum passo falhar, pule para a seção [Troubleshooting](#troubleshooting)
no final.

---

## Pré-requisitos

Antes de começar, garanta que você tem:

- [x] Conta no [Laravel Cloud](https://cloud.laravel.com) (qualquer plano).
- [x] Conta no GitHub com permissão de criar repositórios.
- [x] PHP 8.3+ e Composer instalados localmente.
- [x] CLI Arqel: `composer global require arqel-dev/cli`.
- [x] Git configurado com `user.email` e `user.name`.

---

## Passo 1 — Gerar o template via `arqel cloud:export`

O comando `cloud:export` (entregue em LCLOUD-001) materializa um app Laravel
**pronto para Laravel Cloud** num diretório vazio. Ele inclui:

- `cloud.yaml` com serviços (web, worker, scheduler, Reverb).
- `composer.json` com `arqel-dev/core`, `arqel-dev/auth`, `arqel-dev/fields` e dependências.
- `package.json` com Inertia + React 19.2 + Tailwind v4.
- `app/Providers/PanelServiceProvider.php` registrando o panel default.
- `routes/arqel.php`, `config/arqel.php` e `database/migrations/0000_arqel_base.php`.

```bash
arqel cloud:export ./meu-painel --app-name=meu-painel
```

**Resultado esperado:**

```
Exported 47 files to /home/you/meu-painel

Next steps:
  1. Review the generated files in /home/you/meu-painel
  2. Initialize git:
       cd /home/you/meu-painel
       git init
       git add .
       git commit -m 'Initial Arqel app'
  3. Push to GitHub and click "Deploy to Laravel Cloud" in the README.
```

> Dica: o `--app-name` aceita `[a-zA-Z][a-zA-Z0-9_-]*`. Use o mesmo valor que
> pretende dar ao serviço no Laravel Cloud para manter coerência entre logs
> e dashboards.

---

## Passo 2 — Push para GitHub

```bash
cd ./meu-painel
git init
git add .
git commit -m "feat: initial Arqel app" --signoff
git branch -M main
git remote add origin git@github.com:owner/meu-painel.git
git push -u origin main
```

> **Repositório privado?** Sem problema — o Laravel Cloud pede autorização
> via GitHub App durante o OAuth e tem acesso somente aos repositórios
> que você liberar explicitamente.

---

## Passo 3 — Deploy via `arqel cloud:deploy-link` (recomendado)

A partir de Arqel 0.4 (LCLOUD-004), o CLI gera um link de "Deploy to Laravel Cloud"
com query params já preenchidos:

```bash
arqel cloud:deploy-link owner/meu-painel --region=us-east --name=meu-painel
```

**Saída:**

```
Deploy to Laravel Cloud:
https://cloud.laravel.com/deploy?repo=https%3A%2F%2Fgithub.com%2Fowner%2Fmeu-painel&region=us-east&name=meu-painel

(URL copied to clipboard via xclip.)

Next steps:
  1. Make sure the repository is pushed to GitHub.
  2. Open the URL above and authorise Laravel Cloud (GitHub OAuth).
  3. Confirm the import and configure environment variables.
```

Abra a URL no navegador. O Laravel Cloud:

1. Pede autorização do GitHub App (uma vez por organização).
2. Lê o `cloud.yaml` e provisiona automaticamente:
   - **1× web instance** (PHP-FPM + Octane opcional).
   - **1× queue worker** (`php artisan queue:work redis`).
   - **1× scheduler** (`php artisan schedule:run` em cron).
   - **1× Reverb** (`php artisan reverb:start`).
   - **Postgres** managed (16+).
   - **Redis** managed (queue + cache + Reverb pub/sub).
3. Compila assets via `npm ci && npm run build`.
4. Roda `php artisan key:generate` se `APP_KEY` estiver vazio.

> **Sem o CLI?** Você pode ir direto a https://cloud.laravel.com/deploy e
> selecionar o repositório manualmente. O link gerado pelo CLI é apenas
> uma conveniência.

### Opções suportadas pelo `cloud:deploy-link`

| Flag | Default | Descrição |
| ---- | ------- | --------- |
| `--region=` | `auto` | Região de provisioning (`auto`, `us-east`, `us-west`, `eu-central`, `eu-west`, `ap-southeast`, `sa-east`). |
| `--name=` | (sem default) | Nome do app no dashboard. Aceita `[a-zA-Z][a-zA-Z0-9_-]*`, máx. 40 chars. |

---

## Passo 4 — Configurar variáveis de ambiente

O Laravel Cloud injeta automaticamente:

- `DATABASE_URL` — string de conexão Postgres.
- `REDIS_URL` — string de conexão Redis.
- `APP_URL` — URL pública do app.
- `APP_ENV=production`.

**Você precisa configurar** (no painel → Environment):

| Variável | Valor recomendado | Por quê |
| -------- | ----------------- | ------- |
| `APP_KEY` | (Cloud gera automaticamente) | Encryption key Laravel. |
| `MAIL_MAILER` | `resend` ou `postmark` | Para password reset / convites. |
| `MAIL_FROM_ADDRESS` | `noreply@seu-dominio.com` | Header `From:` em emails transacionais. |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | (gere com `php artisan reverb:install`) | Auth do broadcasting. |
| `ARQEL_PANEL_PATH` | `/admin` | Prefixo das rotas do painel. |
| `ARQEL_AI_OPENAI_KEY` | (opcional) | Se usar `@arqel-dev/ai` com OpenAI. |

> O `cloud:export` já produz um `.env.example` com todos os placeholders.
> Copie-o no dashboard, preencha os valores e clique **Save**.

---

## Passo 5 — Rodar migrations

Por padrão o Laravel Cloud **não roda migrations automaticamente** no primeiro
deploy (para evitar destruir dados em rollbacks). Você precisa disparar
manualmente uma vez:

**Via dashboard** → app → Tasks → New Task:

```
php artisan migrate --force
```

**Via CLI Cloud** (se tiver instalado):

```bash
laravel-cloud task run "php artisan migrate --force" --app=meu-painel
```

Depois do primeiro `migrate`, configure `Auto migrate` no dashboard
(Settings → Deploy → Run migrations on deploy: ON).

### Seed inicial (opcional)

Se você gerou o template com `--with-sample` (reservado para LCLOUD-001
fase 2), rode:

```bash
php artisan db:seed --class=ArqelSampleSeeder --force
```

---

## Verificação final

Acesse `https://meu-painel.laravel.cloud/admin` (ou seu domínio custom).
Você deve ver:

- [x] Tela de login do Arqel (Inertia + Base UI).
- [x] Após login: dashboard vazio com nav lateral.
- [x] Console DevTools sem erros 500/404.
- [x] WebSocket conectado (badge verde no canto inferior, se Reverb estiver OK).

---

## Troubleshooting

### Build failure: "Class 'Arqel\Core\Panel' not found"

Causa: cache de autoload corrompido após instalar plugin novo.
Solução: dispare um deploy com `Clear cache` marcado, ou rode no dashboard:

```bash
composer dump-autoload --optimize
php artisan optimize:clear
```

### "extension xyz not found" durante composer install

Laravel Cloud já vem com PHP 8.3 + extensões padrão (`pdo`, `mbstring`,
`bcmath`, `gd`, `redis`, `intl`, `zip`). Se você precisar de algo exótico
(e.g. `imagick`, `ldap`), adicione no `cloud.yaml`:

```yaml
services:
  web:
    php:
      extensions:
        - imagick
```

### Migration timeout (≥30s)

Migrations grandes (e.g. backfill de coluna nova) podem ultrapassar o limite
de tasks. Use **Maintenance Mode** + run via SSH session:

```bash
php artisan down --secret=temp-token
php artisan migrate --force --timeout=600
php artisan up
```

### Reverb não conecta (badge vermelho)

Cheque variáveis `REVERB_HOST` (deve ser o domínio público sem `https://`),
`REVERB_PORT=443`, `REVERB_SCHEME=https`. O Laravel Cloud termina TLS no edge,
então o Reverb roda atrás do proxy.

### Queue worker travado

```bash
laravel-cloud task run "php artisan queue:restart"
```

O Cloud reinicia workers automaticamente a cada deploy, mas após mudar
`.env` você precisa disparar manualmente.

### "Permission denied" em `storage/`

O Cloud monta `storage/` como volume persistente. Se você fez `chmod` errado
no template, rode no Console SSH:

```bash
chmod -R ug+rwX storage bootstrap/cache
```

### Build OK mas página retorna 502

Cheque os logs em **Logs → web**. Causa mais comum: `APP_KEY` vazio. Solução:

```bash
laravel-cloud task run "php artisan key:generate --force"
```

Depois do `key:generate`, dispare um redeploy.

---

## Próximos passos

- Configure auto-scaling para tráfego variável → ver [auto-scaling.md](./auto-scaling.md).
- Estime custos antes de mover dados de produção → ver [cost-estimation.md](./cost-estimation.md).
- Avalie alternativas (Fly.io, Render, AWS) → ver [comparison-other-hosts.md](./comparison-other-hosts.md).
