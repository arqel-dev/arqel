# Laravel Cloud vs otros hosts — comparación Arqel

Esta página compara el **deploy de una app Arqel** en cinco hosts populares para
Laravel: **Laravel Cloud**, **Fly.io**, **Render**,
**DigitalOcean App Platform** y **AWS App Runner**. El objetivo es ayudarte
a elegir con criterios objetivos, no marketing.

> Criterio: **app Arqel estándar** (Postgres + Redis + Reverb + queue worker),
> tamaño **Medium** (10-50 usuarios, presupuesto ~$100-150/mes).

## Resumen ejecutivo

| Criterio | Laravel Cloud | Fly.io | Render | DO App Platform | AWS App Runner |
| --------- | ------------- | ------ | ------ | --------------- | -------------- |
| **DX para Laravel** | ★★★★★ | ★★★ | ★★★ | ★★★ | ★★ |
| **Tiempo de setup** | <5 min | ~30 min | ~20 min | ~25 min | ~2 h |
| **Costo Medium** | $120/mes | $95/mes | $110/mes | $80/mes | $140/mes |
| **Octane soportado** | Sí, 1-click | Sí, manual | Sí, manual | Parcial | Sí, ECS |
| **Reverb 1-click** | Sí | Manual | Manual | Manual | Manual |
| **Postgres gestionado** | Sí | Sí | Sí | Sí | Sí (RDS) |
| **Redis gestionado** | Sí | Manual o Upstash | Sí | Sí | Manual |
| **Auto-scaling** | Sí, nativo | Sí | Sí | Sí | Sí |
| **GitHub CI/CD** | Sí, nativo | GitHub Actions | Sí, nativo | Sí, nativo | Sí, nativo |
| **Edge regions** | 7 | 35+ | 5 | 12 | 30+ |

## Laravel Cloud

**Fortalezas:**

- Único host con **Reverb y queue workers como ciudadanos de primera clase**.
- El `cloud.yaml` producido por `arqel cloud:export` es el camino más corto:
  cero configuración manual.
- Postgres + Redis + storage en el mismo dashboard.
- Termina TLS en el edge automáticamente; certificado custom en 1 click.

**Debilidades:**

- **Solo 7 regiones** — sin cobertura en África u Oceanía más allá de Sydney.
- Pricing atado a tier — sin "pay-per-second" granular como Fly.
- Lock-in moderado: `cloud.yaml` no es portable.

**Cuándo elegir:** cuando tu equipo es mayoritariamente PHP/Laravel y no quiere
gastar tiempo aprendiendo Docker/K8s. Los paneles admin aterrizan aquí en la mayoría de casos.

## Fly.io

**Fortalezas:**

- **35+ edge regions** — latencia global excepcional.
- Pricing **pay-per-second** — apaga instancias idle y paga centavos.
- `fly.toml` es portable y auditable (vive en el repo).
- Soporte para `firecracker microVMs` — boot en <1s.

**Debilidades:**

- **Reverb requiere setup manual** con Redis externo (Upstash) o Fly Redis.
- El Postgres gestionado (`fly pg`) sigue en "developer preview" en algunos tiers
  y tiene límites de storage menos predecibles que RDS.
- Laravel DX **no es first-class** — eres una app Docker más.
- Los queue workers requieren instancias `fly machine` separadas con su propia config.

**Cuándo elegir:** cuando importa la latencia global (ej., un panel SaaS con
clientes en 5 continentes) o cuando el presupuesto es **muy ajustado** y estás
dispuesto a configurar las cosas manualmente.

**Setup aproximado de Fly.io para Arqel:**

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

**Fortalezas:**

- DX limpia: dashboard pulido, **Blueprints** versionados (`render.yaml`).
- Postgres + Redis gestionados sólidos incluidos.
- **Background workers** como recurso de primera clase.
- Free tier generoso para uso hobby.

**Debilidades:**

- Sin Reverb integrado — lo corres como un `worker` separado, pero el load
  balancer **no es sticky-session** por defecto; debes usar el "Web Service" de Render
  y configurarlo manualmente.
- Solo 5 regiones.
- Cold starts en planes inferiores (~30s en el free tier).

**Cuándo elegir:** proyectos hobby/MVP que quieren más pulido que Fly sin
pagar Laravel Cloud, y que toleran configurar Reverb manualmente.

## DigitalOcean App Platform

**Fortalezas:**

- **El más barato de las opciones gestionadas** en el tier Medium (~$80/mes).
- Integración nativa con Spaces (S3-compatible) — bueno para uploads Arqel.
- Bases de datos DigitalOcean sólidas (Postgres + Redis).
- 12 regiones.

**Debilidades:**

- DX genérica (no específica de Laravel). Escribes `app.yaml` a mano.
- **Componentes worker aún limitados**: máx. 1 instancia en planes inferiores.
- Reverb funciona pero requiere un "Internal Service" + sticky session manual
  vía el load balancer DO (cargo extra).

**Cuándo elegir:** presupuesto ajustado, equipo ya familiarizado con DO, sin
requisitos pesados de Reverb/realtime.

## AWS App Runner

**Fortalezas:**

- Integración nativa con AWS (RDS, ElastiCache, S3, CloudFront).
- **30+ regiones globales**.
- Auto-scaling sólido + IAM granular.

**Debilidades:**

- **Setup brutal** para una Arqel simple: terminas escribiendo CDK o Terraform
  porque la UI de la consola no logra coordinar Reverb + Worker + RDS + ElastiCache +
  Secrets Manager.
- App Runner no tiene **procesos worker** — necesitas un ECS Fargate separado
  para la queue, duplicando complejidad.
- Costo final típicamente 10-30% sobre Laravel Cloud.
- Cold start de container en deploys grandes (>3 min).

**Cuándo elegir:** la empresa ya está all-in en AWS, el equipo tiene
expertise IaC y la gobernanza/compliance demanda AWS-native (SOC 2 estricto, HIPAA).

## Cuándo elegir cada uno — guía de decisión

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

## Migración entre hosts

Arqel es **portable por diseño** — ninguna feature depende de Laravel Cloud
específicamente. Lo que cambia entre hosts es solo:

- **Archivo de config:** `cloud.yaml` (LC), `fly.toml` (Fly), `render.yaml`
  (Render), `app.yaml` (DO), `apprunner.yaml` (AWS).
- **Variables de entorno:** `DATABASE_URL` es universal; `REVERB_HOST`
  varía.
- **Procfiles o procesos:** Laravel Cloud corre procesos vía `cloud.yaml`
  services; Fly vía `[processes]`; AWS vía task definitions.

Para migrar, empieza con `arqel cloud:export` y adapta el servicio de deploy.
Los PRs con templates para otros hosts (Fly, Render) son bienvenidos en el
[repositorio](https://github.com/arqel-dev/arqel).

## Próximos pasos

- Configura auto-scaling en la plataforma elegida → [auto-scaling.md](./auto-scaling.md).
- Estima el total cost of ownership → [cost-estimation.md](./cost-estimation.md).
