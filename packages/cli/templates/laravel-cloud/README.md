# {{APP_NAME}}

Aplicação Arqel pronta para deploy em [Laravel Cloud](https://cloud.laravel.com).

[![Deploy to Laravel Cloud](https://laravel.cloud/button.svg)](https://laravel.cloud/deploy?repo=your-org/{{APP_NAME}})

## Pre-flight checklist

Antes de clicar em "Deploy to Laravel Cloud", verifique:

- [ ] Repositório foi pushado para o seu GitHub (ou GitLab/Bitbucket suportado)
- [ ] Você tem uma conta em [Laravel Cloud](https://cloud.laravel.com)
- [ ] `cloud.yml` está com nome de app desejado
- [ ] `.env.production.example` foi revisado — qualquer secret deve ser definido como Environment Variable no painel da Laravel Cloud, **não commitado**
- [ ] `composer.json` tem o `name` correto (`vendor/app-name`)

## Deploy manual

Caso prefira não usar o botão:

```bash
# 1. Crie o app no painel Laravel Cloud apontando para o repositório
# 2. Configure environment variables (APP_KEY, DB credentials, REVERB_APP_KEY, etc.)
# 3. Push para a branch principal — Laravel Cloud detecta cloud.yml e provisiona automaticamente
git push origin main
```

## Stack provisionada

A `cloud.yml` deste template provisiona:

- **PHP 8.3** com extensões padrão do Laravel
- **Postgres 16** com extensão `pgvector` (necessária para Arqel AI fields)
- **Redis** para cache, queue, session e broadcast
- **Reverb** para WebSockets (Arqel realtime widgets)

## Customizar

- `app/Providers/ArqelServiceProvider.php` — registre seus Resources e Widgets aqui
- `cloud.yml` — ajuste serviços conforme necessário
- `composer.json` — adicione plugins do marketplace Arqel

## Custom domain

1. No painel Laravel Cloud, vá em **Settings → Domains**
2. Adicione seu domínio (ex.: `admin.exemplo.com`)
3. Configure o registro DNS CNAME apontando para o domínio Cloud sugerido
4. Aguarde propagação e SSL automático (Let's Encrypt)
5. Atualize `APP_URL` na seção **Environment Variables**

## Troubleshooting

### Build falha em "composer install"

Verifique se `arqel-dev/*` está disponível no Packagist (durante beta, pode ser necessário adicionar `repositories` apontando para `vcs` no `composer.json`).

### Reverb não conecta

- Confirme que `REVERB_APP_KEY` está definido no painel
- Cheque que `BROADCAST_CONNECTION=reverb` no `.env`
- Veja logs do serviço `reverb` no painel Cloud

### Postgres pgvector ausente

Certifique-se de que `cloud.yml` lista a extensão `pgvector` em `services.postgres.extensions`. Após provisionar, rode no console SQL: `CREATE EXTENSION IF NOT EXISTS vector;`

### Migrations não rodam

Adicione um post-deploy hook no painel ou em `cloud.yml`:

```yaml
hooks:
  post-deploy:
    - php artisan migrate --force
    - php artisan arqel:install
```

## Próximos passos

- Documentação Arqel: <https://arqel.dev/docs>
- Documentação Laravel Cloud: <https://cloud.laravel.com/docs>
- Issues / suporte: <https://github.com/arqel-dev/arqel/issues>
