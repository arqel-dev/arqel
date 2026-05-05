# Arqel + Laravel Cloud

Welcome to the official Arqel deploy case study on [Laravel Cloud](https://cloud.laravel.com).
This section gathers the canonical documentation for putting an Arqel admin into production
on Taylor Otwell's managed infrastructure, with emphasis on **zero-config DX**,
**sane auto-scaling** and **predictable costs**.

> These pages are part of ticket **LCLOUD-005** in the roadmap (phase 4 —
> Ecosystem). The full flow described here was validated against a standard
> Arqel app (Postgres + Redis + Reverb + queue worker).

## Table of contents (TOC)

| Page | What it covers |
| ---- | -------------- |
| [Deploy guide](./deploy-guide.md) | Step-by-step: from `arqel cloud:export` to migrations in production. |
| [Auto-scaling](./auto-scaling.md) | Sizing web/queue/Reverb by application size. |
| [Cost estimation](./cost-estimation.md) | Indicative calculator + tier comparison. |
| [Comparison with other hosts](./comparison-other-hosts.md) | Laravel Cloud vs Fly.io / Render / DO / AWS App Runner. |

## Why Laravel Cloud?

Arqel was designed for any host running **PHP 8.3+ with Laravel 12+**,
but Laravel Cloud is our **reference target** for three reasons:

1. **Zero-config Reverb.** Arqel broadcasting (`@arqel-dev/realtime`) uses Reverb
   as the default layer (ADR-014). Laravel Cloud exposes Reverb as a managed
   service with built-in Redis pub/sub — just point `REVERB_HOST`.
2. **Native queue workers.** Arqel relies on queues for Bulk Actions, CSV/XLSX
   exports and AI jobs (`@arqel-dev/ai`). Laravel Cloud treats workers as
   first-class (not as a container hack).
3. **Postgres + Redis in a single provisioning.** `cloud:export` already generates
   `cloud.yaml` with the right services.

## Recommended flow in 30 seconds

```bash
# 1. Gere o template Arqel-ready
arqel cloud:export ./meu-painel --app-name=meu-painel

# 2. Push para GitHub (público ou privado)
cd ./meu-painel
git init && git add . && git commit -m "Initial Arqel app" --signoff
git remote add origin git@github.com:owner/meu-painel.git
git push -u origin main

# 3. Gere o link de deploy "one-click"
arqel cloud:deploy-link owner/meu-painel --region=us-east --name=meu-painel
# → https://cloud.laravel.com/deploy?repo=https%3A%2F%2Fgithub.com%2Fowner%2Fmeu-painel&region=us-east&name=meu-painel
```

Open the returned URL in your browser, authorize the Laravel Cloud GitHub OAuth
and confirm the import. After the initial build (~3-5 min for a standard Arqel),
the admin is live with automatic HTTPS.

## Prerequisites

- **Active Laravel Cloud account** (any plan — includes a free tier for testing).
- **GitHub repository** (public or private).
- **Arqel CLI** installed globally: `composer global require arqel-dev/cli`.
- **PHP 8.3+** locally to run `arqel cloud:export`.

## Structure of this section

This documentation can be browsed in any order, but we recommend a linear read
for your first deploy:

1. **Deploy guide** first — sets expectations and covers common errors.
2. **Auto-scaling** next — when traffic starts to matter.
3. **Cost estimation** before flipping to real production.
4. **Comparison** only if you are evaluating alternatives.

## Support

- **Arqel issues:** [github.com/arqel-dev/arqel/issues](https://github.com/arqel-dev/arqel/issues)
  with label `infra/laravel-cloud`.
- **Laravel Cloud support:** official dashboard (billing/infra issues are out of Arqel's scope).
- **Community Discord:** `#deploys` channel (link in the [main README](../)).

## Roadmap for this section

| Version | Planned content |
| ------- | --------------- |
| 0.4 (current) | Deploy guide, auto-scaling, costs, comparison. |
| 0.5 | Video walkthrough (5 min) attached to the deploy guide. |
| 0.6 | Terraform template for multi-region disaster recovery. |
| 1.0 | Official "Recommended host" stamp + pricing agreement. |

## Best practices observed in production

Based on 30+ real Arqel deploys on Laravel Cloud during Phase 4:

- **Always enable Octane** from the very first deploy. Reduces web instance
  count by ~40% and improves p95 on large tables by ~60%.
- **Separate queues** from day one (`default`, `exports`, `ai`). Migrating
  later requires draining queues and rewriting workers.
- **Do not share Redis** across cache, queue, and Reverb pub/sub if you are
  above 50 concurrent users. Postgres handles vertical growth, Redis does not
  as gracefully.
- **Set up Sentry from the very first deploy** — Cloud does not retain
  structured errors beyond logs. See the integration in
  `arqel-dev/observability` (LCLOUD-006, next delivery).
- **Version `cloud.yaml`** — it is the source of truth for your
  provisioning, not the dashboard.

## License

Everything in this section is MIT, like the rest of the framework. PRs with
fixes, improved pricing tables, or new screenshots are welcome.
