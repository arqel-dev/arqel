# Estimativa de custos — Arqel no Laravel Cloud

> **Disclaimer:** os preços abaixo são **orientativos** e refletem a tabela
> pública do Laravel Cloud em **abril de 2026**. Sempre confirme valores
> atualizados em [cloud.laravel.com/pricing](https://cloud.laravel.com/pricing)
> antes de comprometer orçamento. Esta página é parte do case study LCLOUD-005.

## Estrutura de custos

Um app Arqel em produção paga por **cinco categorias**:

1. **Compute** (web + workers + Reverb) — por hora de instância.
2. **Database** (Postgres managed) — por tier mensal.
3. **Cache/Queue/PubSub** (Redis managed) — por tier mensal.
4. **Egress** (banda saída) — por GB.
5. **Add-ons Arqel-specific:**
   - Tokens de IA (`@arqel/ai` com OpenAI/Anthropic).
   - Conexões Reverb (incluído no compute, mas afeta dimensionamento).
   - Storage S3-compatible para uploads de Field `FileField`.

## Tabela referencial Laravel Cloud (abril 2026)

| Recurso | Tier | Preço aprox./mês |
| ------- | ---- | ---------------- |
| Web instance `nano` (256 MB) | — | $5 |
| Web instance `small` (512 MB) | — | $12 |
| Web instance `medium` (1 GB) | — | $24 |
| Web instance `large` (2 GB) | — | $48 |
| Postgres Hobby | 256 MB / 1 GB storage | $0 (free tier) |
| Postgres Starter | 1 GB / 10 GB | $20 |
| Postgres Production | 4 GB / 50 GB | $80 |
| Postgres Scale | 16 GB / 200 GB | $300 |
| Redis Hobby | 25 MB | $0 |
| Redis Starter | 256 MB | $15 |
| Redis Production | 1 GB | $50 |
| Egress | — | $0.10/GB (após 100 GB grátis) |

Add-ons fora do Cloud:

| Add-on | Custo aprox. |
| ------ | ------------ |
| OpenAI GPT-4o (entrada) | $2.50 / 1M tokens |
| OpenAI GPT-4o (saída) | $10 / 1M tokens |
| Anthropic Claude Sonnet 4.7 (entrada) | $3 / 1M tokens |
| Anthropic Claude Sonnet 4.7 (saída) | $15 / 1M tokens |
| S3-compatible storage (B2/R2) | ~$5 / 100 GB |

## Calculadora — três cenários

### Small — ferramenta interna (≤10 usuários)

| Item | Quantidade | Mensal |
| ---- | ---------- | ------ |
| Web `nano` × 1 | 24×7 | $5 |
| Worker `nano` × 1 | 24×7 | $5 |
| Reverb `nano` × 1 | 24×7 | $5 |
| Postgres Hobby | — | $0 |
| Redis Hobby | — | $0 |
| Egress (~5 GB) | — | $0 |
| **Total Cloud** | | **$15** |
| AI tokens (raros, ~100k tokens) | | $1 |
| **Total** | | **~$16/mês** |

### Medium — SaaS B2B (10-50 usuários, AI ocasional)

| Item | Quantidade | Mensal |
| ---- | ---------- | ------ |
| Web `small` × 2 | auto 1-3 | $24-36 |
| Worker `small` × 2 | auto 1-3 | $24-36 |
| Reverb `small` × 1 | 24×7 | $12 |
| Postgres Starter | — | $20 |
| Redis Starter | — | $15 |
| Egress (~50 GB) | — | $0 |
| **Total Cloud** | | **~$95-120** |
| AI tokens (~5M tokens entrada + 1M saída) | | $22 |
| **Total** | | **~$120-145/mês** |

### Large — produto público (50-200 usuários, AI heavy)

| Item | Quantidade | Mensal |
| ---- | ---------- | ------ |
| Web `medium` × 4 | auto 4-8 | $96-192 |
| Worker `medium` × 4 | auto 4-8 | $96-192 |
| AI worker `medium` × 2 | auto 2-4 | $48-96 |
| Reverb `medium` × 2 | sticky | $48 |
| Postgres Production | — | $80 |
| Redis Production | — | $50 |
| Egress (~500 GB) | — | $40 |
| **Total Cloud** | | **~$460-700** |
| AI tokens (~50M entrada + 15M saída) | | $350 |
| Storage S3 (200 GB) | — | $10 |
| **Total** | | **~$820-1.060/mês** |

## Otimizações de custo específicas do Arqel

1. **Cache table queries.** O `@arqel/table` usa `cache()->remember()` por
   padrão para queries de `getRecords()`. Aumente o TTL para 60s em tabelas
   de baixa volatilidade — derruba 70% das queries Postgres.
2. **AI prompt caching.** Use Anthropic prompt caching (5 min TTL) para
   reduzir 90% do custo de tokens entrada em conversas longas. Já default
   no `ClaudeProvider`.
3. **Exports em fila baixa-prioridade.** Rode `exports` queue em workers
   `nano` separados — barato e não compete com web por CPU.
4. **Octane.** Liga no `cloud.yaml` (`octane: true`). Reduz necessidade de
   web instances em ~40%.
5. **Postgres connection pooling com pgbouncer.** Já default — não desligue.

## Comparação rápida com competidores

Para o cenário **Medium** acima (~$120/mês no Laravel Cloud):

| Plataforma | Custo equivalente | Esforço de setup |
| ---------- | ----------------- | ---------------- |
| **Laravel Cloud** | **$120** | Zero (`cloud:export`) |
| Fly.io | ~$95 | Médio (escrever `fly.toml`) |
| Render | ~$110 | Baixo (Blueprint) |
| DigitalOcean App Platform | ~$80 | Médio (App Spec) |
| AWS App Runner + RDS | ~$140 | Alto (CDK ou Terraform) |
| Heroku | ~$200 | Baixo |

> Detalhes em [comparison-other-hosts.md](./comparison-other-hosts.md).

## Quando não vale a pena

Laravel Cloud é caro **se**:

- Você tem ≤3 usuários e tudo cabe num droplet de $6 da DigitalOcean.
- Sua app é **stateless e write-heavy** — Lambda + RDS sai mais barato.
- Você precisa de regiões fora das suportadas (especialmente África/Oceania
  além de `ap-southeast`).

Nesses casos, considere [Fly.io](https://fly.io) ou self-host no
DigitalOcean com Forge.

## Próximos passos

- Reveja dimensionamento de cada componente → [auto-scaling.md](./auto-scaling.md).
- Compare detalhadamente com outros hosts → [comparison-other-hosts.md](./comparison-other-hosts.md).
