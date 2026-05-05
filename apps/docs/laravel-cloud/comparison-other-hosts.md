# Laravel Cloud vs other hosts — Arqel comparison

This page compares the **deploy of an Arqel app** across five popular Laravel
hosts: **Laravel Cloud**, **Fly.io**, **Render**,
**DigitalOcean App Platform**, and **AWS App Runner**. The goal is to help
you choose with objective criteria, not marketing.

> Criterion: **standard Arqel app** (Postgres + Redis + Reverb + queue worker),
> **Medium** size (10-50 users, ~$100-150/month budget).

## Executive summary

| Criterion | Laravel Cloud | Fly.io | Render | DO App Platform | AWS App Runner |
| --------- | ------------- | ------ | ------ | --------------- | -------------- |
| **DX for Laravel** | ★★★★★ | ★★★ | ★★★ | ★★★ | ★★ |
| **Setup time** | <5 min | ~30 min | ~20 min | ~25 min | ~2 h |
| **Medium cost** | $120/month | $95/month | $110/month | $80/month | $140/month |
| **Octane supported** | Yes, 1-click | Yes, manual | Yes, manual | Partial | Yes, ECS |
| **Reverb 1-click** | Yes | Manual | Manual | Manual | Manual |
| **Managed Postgres** | Yes | Yes | Yes | Yes | Yes (RDS) |
| **Managed Redis** | Yes | Manual or Upstash | Yes | Yes | Manual |
| **Auto-scaling** | Yes, native | Yes | Yes | Yes | Yes |
| **GitHub CI/CD** | Yes, native | GitHub Actions | Yes, native | Yes, native | Yes, native |
| **Edge regions** | 7 | 35+ | 5 | 12 | 30+ |

## Laravel Cloud

**Strengths:**

- Only host with **Reverb and queue workers as first-class citizens**.
- The `cloud.yaml` produced by `arqel cloud:export` is the shortest path:
  zero manual configuration.
- Postgres + Redis + storage all in the same dashboard.
- Terminates TLS at the edge automatically; custom certificate in 1 click.

**Weaknesses:**

- **Only 7 regions** — no coverage in Africa or Oceania beyond Sydney.
- Pricing tied to tier — no granular "pay-per-second" like Fly.
- Moderate lock-in: `cloud.yaml` is not portable.

**When to pick:** when your team is mostly PHP/Laravel and does not want to
spend time learning Docker/K8s. Admin panels land here in most cases.

## Fly.io

**Strengths:**

- **35+ edge regions** — exceptional global latency.
- **Pay-per-second** pricing — turn off idle instances and pay pennies.
- `fly.toml` is portable and auditable (lives in the repo).
- Support for `firecracker microVMs` — boot in <1s.

**Weaknesses:**

- **Reverb requires manual setup** with external Redis (Upstash) or Fly Redis.
- Managed Postgres (`fly pg`) is still in "developer preview" for some tiers
  and has less predictable storage limits than RDS.
- Laravel DX is **not first-class** — you are just one more Docker app.
- Queue workers require separate `fly machine` instances with their own config.

**When to pick:** when global latency matters (e.g., a SaaS panel with
customers across 5 continents) or when the budget is **very tight** and you
are willing to configure things manually.

**Approximate Fly.io setup for Arqel:**

```toml
# fly.toml
app = "meu-painel"
primary_region = "gru"

[build]
  dockerfile = "Dockerfile"

[[services]]
  internal_port = 8000
  protocol = "tcp"

  [[services.ports]]
    port = 443
    handlers = ["tls", "http"]

[processes]
  app = "php artisan octane:start --host=0.0.0.0 --port=8000"
  worker = "php artisan queue:work redis"
  reverb = "php artisan reverb:start --host=0.0.0.0 --port=8080"
```

## Render

**Strengths:**

- Clean DX: polished dashboard, versioned **Blueprints** (`render.yaml`).
- Solid managed Postgres + Redis included.
- **Background workers** as a first-class resource.
- Generous free tier for hobby use.

**Weaknesses:**

- No integrated Reverb — you run it as a separate `worker`, but the load
  balancer is **not sticky-session** by default; you must use Render's
  "Web Service" and configure it manually.
- Only 5 regions.
- Cold starts on lower plans (~30s on the free tier).

**When to pick:** hobby/MVP projects that want more polish than Fly without
paying for Laravel Cloud, and that tolerate configuring Reverb manually.

## DigitalOcean App Platform

**Strengths:**

- **Cheapest of the managed options** at the Medium tier (~$80/month).
- Native integration with Spaces (S3-compatible) — good for Arqel uploads.
- Solid DigitalOcean databases (Postgres + Redis).
- 12 regions.

**Weaknesses:**

- Generic DX (not Laravel-specific). You write `app.yaml` by hand.
- **Worker components are still limited**: max 1 instance on lower plans.
- Reverb works but requires an "Internal Service" + manual sticky session
  via the DO load balancer (extra charge).

**When to pick:** tight budget, team already familiar with DO, no heavy
Reverb/realtime requirements.

## AWS App Runner

**Strengths:**

- Native integration with AWS (RDS, ElastiCache, S3, CloudFront).
- **30+ global regions**.
- Solid auto-scaling + granular IAM.

**Weaknesses:**

- **Brutal setup** for a simple Arqel: you end up writing CDK or Terraform
  because the console UI cannot juggle Reverb + Worker + RDS + ElastiCache +
  Secrets Manager.
- App Runner has no **worker processes** — you need a separate ECS Fargate
  for the queue, doubling complexity.
- Final cost typically 10-30% above Laravel Cloud.
- Container cold start on large deploys (>3 min).

**When to pick:** the company is already all-in on AWS, the team has IaC
expertise, and governance/compliance demands AWS-native (strict SOC 2, HIPAA).

## When to pick each — decision guide

```
└─ Você quer rodar um Arqel em produção?
   ├─ Time PHP-only, painel admin tradicional?
   │  └─ Laravel Cloud (default recomendado)
   │
   ├─ Latência global (clientes em 5 continentes)?
   │  └─ Fly.io
   │
   ├─ Orçamento ≤$80/mês, sem Reverb pesado?
   │  └─ DigitalOcean App Platform
   │
   ├─ MVP / hobby, free tier importante?
   │  └─ Render
   │
   ├─ Compliance AWS-native obrigatória?
   │  └─ AWS App Runner + ECS Fargate
   │
   └─ Quer self-host num droplet $6?
      └─ Forge + DigitalOcean (fora do escopo desta seção)
```

## Migration between hosts

Arqel is **portable by design** — no feature depends on Laravel Cloud
specifically. What changes between hosts is only:

- **Config file:** `cloud.yaml` (LC), `fly.toml` (Fly), `render.yaml`
  (Render), `app.yaml` (DO), `apprunner.yaml` (AWS).
- **Environment variables:** `DATABASE_URL` is universal; `REVERB_HOST`
  varies.
- **Procfiles or processes:** Laravel Cloud runs processes via `cloud.yaml`
  services; Fly via `[processes]`; AWS via task definitions.

To migrate, start with `arqel cloud:export` and adapt the deploy service.
PRs with templates for other hosts (Fly, Render) are welcome in the
[repository](https://github.com/arqel-dev/arqel).

## Next steps

- Configure auto-scaling on your chosen platform → [auto-scaling.md](./auto-scaling.md).
- Estimate total cost of ownership → [cost-estimation.md](./cost-estimation.md).
