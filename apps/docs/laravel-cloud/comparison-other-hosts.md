# Laravel Cloud vs outros hosts — comparação Arqel

Esta página compara o **deploy de um app Arqel** em cinco plataformas
populares para Laravel: **Laravel Cloud**, **Fly.io**, **Render**,
**DigitalOcean App Platform** e **AWS App Runner**. O objetivo é
ajudar você a escolher com critérios objetivos, não publicidade.

> Critério: **app Arqel padrão** (Postgres + Redis + Reverb + queue worker),
> tamanho **Medium** (10-50 usuários, ~$100-150/mês de orçamento).

## Resumo executivo

| Critério | Laravel Cloud | Fly.io | Render | DO App Platform | AWS App Runner |
| -------- | ------------- | ------ | ------ | --------------- | -------------- |
| **DX para Laravel** | ★★★★★ | ★★★ | ★★★ | ★★★ | ★★ |
| **Setup time** | <5 min | ~30 min | ~20 min | ~25 min | ~2 h |
| **Custo Medium** | $120/mês | $95/mês | $110/mês | $80/mês | $140/mês |
| **Octane suportado** | Sim, 1-click | Sim, manual | Sim, manual | Parcial | Sim, ECS |
| **Reverb 1-click** | Sim | Manual | Manual | Manual | Manual |
| **Postgres managed** | Sim | Sim | Sim | Sim | Sim (RDS) |
| **Redis managed** | Sim | Manual ou Upstash | Sim | Sim | Manual |
| **Auto-scaling** | Sim, nativo | Sim | Sim | Sim | Sim |
| **CI/CD GitHub** | Sim, nativo | GitHub Actions | Sim, nativo | Sim, nativo | Sim, nativo |
| **Edge regions** | 7 | 35+ | 5 | 12 | 30+ |

## Laravel Cloud

**Pontos fortes:**

- Único host com **Reverb e queue workers como first-class citizens**.
- `cloud.yaml` produzido pelo `arqel cloud:export` é o caminho mais
  curto: zero configuração manual.
- Postgres + Redis + storage tudo no mesmo dashboard.
- Termina TLS no edge automaticamente; certificado custom em 1 click.

**Pontos fracos:**

- **Apenas 7 regiões** — sem cobertura na África ou Oceania além de Sydney.
- Pricing atrelado ao tier — sem "pay-per-second" granular como Fly.
- Lock-in moderado: `cloud.yaml` não é portável.

**Quando escolher:** quando seu time é majoritariamente PHP/Laravel e
não quer perder tempo aprendendo Docker/K8s. Painéis admin batem aqui
na maioria dos casos.

## Fly.io

**Pontos fortes:**

- **35+ regiões edge** — latência global excepcional.
- Pricing **pay-per-second** — desligue instâncias ociosas e pague centavos.
- `fly.toml` é portável e auditável (vive no repo).
- Suporte a `firecracker microVMs` — boot em <1s.

**Pontos fracos:**

- **Reverb requer setup manual** com Redis externo (Upstash) ou Fly Redis.
- Postgres managed (`fly pg`) ainda em "developer preview" para alguns tiers
  e tem limites de storage menos previsíveis que RDS.
- DX para Laravel **não é first-class** — você é apenas mais um app Docker.
- Queue workers exigem `fly machine` separados, com sua própria configuração.

**Quando escolher:** quando latência global importa (e.g., painel SaaS
com clientes em 5 continentes) ou quando o orçamento é **muito apertado**
e você está disposto a configurar manualmente.

**Setup approximado para Arqel em Fly.io:**

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

**Pontos fortes:**

- DX limpa: dashboard polido, **Blueprints** (`render.yaml`) versionado.
- Postgres managed sólido + Redis incluído.
- **Background workers** como recurso first-class.
- Free tier generoso para hobby.

**Pontos fracos:**

- Sem Reverb integrado — você roda como `worker` separado, mas o load
  balancer **não sticky session** por default; precisa usar Render's
  "Web Service" e configurar manualmente.
- Apenas 5 regiões.
- Cold start em planos baixos (~30s no free tier).

**Quando escolher:** projetos hobby/MVP que querem mais polish que Fly
sem pagar Laravel Cloud, e que toleram manualmente configurar Reverb.

## DigitalOcean App Platform

**Pontos fortes:**

- **Mais barato dos managed** para tier Medium (~$80/mês).
- Integração nativa com Spaces (S3-compatible) — bom para uploads do Arqel.
- DigitalOcean databases (Postgres + Redis) sólidos.
- 12 regiões.

**Pontos fracos:**

- DX genérica (não Laravel-specific). Você escreve `app.yaml` na mão.
- **Worker components ainda limitados**: max 1 instância em planos baixos.
- Reverb funciona mas requer "Internal Service" + sticky session manual
  via DO load balancer (cobrança extra).

**Quando escolher:** orçamento apertado, equipe já familiarizada com DO,
sem requisitos pesados de Reverb/realtime.

## AWS App Runner

**Pontos fortes:**

- Integração nativa com AWS (RDS, ElastiCache, S3, CloudFront).
- **30+ regiões** globais.
- Auto-scaling sólido + IAM granular.

**Pontos fracos:**

- **Setup brutal** para um simples Arqel: você acaba escrevendo CDK ou
  Terraform porque o console UI não dá conta de Reverb + Worker + RDS
  + ElastiCache + Secrets Manager.
- App Runner não tem **worker processes** — você precisa de ECS Fargate
  separado para queue, dobrando complexidade.
- Custo final tipicamente 10-30% acima do Laravel Cloud.
- Cold start de containers em deploys grandes (>3 min).

**Quando escolher:** a empresa já está toda em AWS, time tem competência
em IaC, e governance/compliance exige AWS-native (SOC 2, HIPAA estritos).

## Quando escolher cada um — guia decisório

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

## Migração entre hosts

O Arqel é **portável por construção** — nenhuma feature depende de Laravel
Cloud especificamente. O que muda entre hosts é apenas:

- **Arquivo de config:** `cloud.yaml` (LC), `fly.toml` (Fly), `render.yaml`
  (Render), `app.yaml` (DO), `apprunner.yaml` (AWS).
- **Variáveis de ambiente:** `DATABASE_URL` é universal; mas `REVERB_HOST`
  varia.
- **Procfiles ou processos:** Laravel Cloud roda processos via `cloud.yaml`
  services; Fly via `[processes]`; AWS via task definitions.

Para migrar, comece pelo `arqel cloud:export` e adapte o serviço de
deploy. PRs com templates para outros hosts (Fly, Render) são bem-vindos
no [repositório](https://github.com/arqel/arqel).

## Próximos passos

- Configure auto-scaling na sua plataforma escolhida → [auto-scaling.md](./auto-scaling.md).
- Estime o custo total de propriedade → [cost-estimation.md](./cost-estimation.md).
