# Arqel + Laravel Cloud

Bienvenido al case study oficial de deploy de Arqel en [Laravel Cloud](https://cloud.laravel.com).
Esta sección reúne la documentación canónica para poner un admin Arqel en producción
sobre la infraestructura gestionada de Taylor Otwell, con énfasis en **DX zero-config**,
**auto-scaling sensato** y **costos predecibles**.

> Estas páginas forman parte del ticket **LCLOUD-005** del roadmap (fase 4 —
> Ecosistema). El flujo completo descrito aquí fue validado contra una app Arqel
> estándar (Postgres + Redis + Reverb + queue worker).

## Tabla de contenidos (TOC)

| Página | Qué cubre |
| ---- | -------------- |
| [Guía de deploy](./deploy-guide.md) | Paso a paso: desde `arqel cloud:export` hasta migraciones en producción. |
| [Auto-scaling](./auto-scaling.md) | Sizing de web/queue/Reverb por tamaño de aplicación. |
| [Estimación de costos](./cost-estimation.md) | Calculadora indicativa + comparación de tiers. |
| [Comparación con otros hosts](./comparison-other-hosts.md) | Laravel Cloud vs Fly.io / Render / DO / AWS App Runner. |

## ¿Por qué Laravel Cloud?

Arqel fue diseñado para cualquier host que ejecute **PHP 8.3+ con Laravel 12+**,
pero Laravel Cloud es nuestro **target de referencia** por tres razones:

1. **Reverb zero-config.** El broadcasting de Arqel (`@arqel-dev/realtime`) usa Reverb
   como capa por defecto (ADR-014). Laravel Cloud expone Reverb como un servicio
   gestionado con Redis pub/sub incluido — solo apunta `REVERB_HOST`.
2. **Queue workers nativos.** Arqel se apoya en queues para Bulk Actions, exports
   CSV/XLSX y jobs IA (`@arqel-dev/ai`). Laravel Cloud trata los workers como
   first-class (no como un hack de container).
3. **Postgres + Redis en un solo provisioning.** `cloud:export` ya genera
   `cloud.yaml` con los servicios correctos.

## Flujo recomendado en 30 segundos

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

Abre la URL devuelta en tu browser, autoriza el GitHub OAuth de Laravel Cloud
y confirma el import. Tras el build inicial (~3-5 min para una Arqel estándar),
el admin queda live con HTTPS automático.

## Prerrequisitos

- **Cuenta Laravel Cloud activa** (cualquier plan — incluye un free tier para testing).
- **Repositorio GitHub** (público o privado).
- **Arqel CLI** instalado globalmente: `composer global require arqel-dev/cli`.
- **PHP 8.3+** local para ejecutar `arqel cloud:export`.

## Estructura de esta sección

Esta documentación puede leerse en cualquier orden, pero recomendamos una lectura
lineal para tu primer deploy:

1. **Guía de deploy** primero — fija expectativas y cubre errores comunes.
2. **Auto-scaling** después — cuando el tráfico empieza a importar.
3. **Estimación de costos** antes de pasar a producción real.
4. **Comparación** solo si estás evaluando alternativas.

## Soporte

- **Issues de Arqel:** [github.com/arqel-dev/arqel/issues](https://github.com/arqel-dev/arqel/issues)
  con label `infra/laravel-cloud`.
- **Soporte de Laravel Cloud:** dashboard oficial (issues de billing/infra están fuera del scope de Arqel).
- **Discord de la comunidad:** canal `#deploys` (link en el [README principal](../)).

## Roadmap de esta sección

| Versión | Contenido planeado |
| ------- | --------------- |
| 0.4 (actual) | Guía de deploy, auto-scaling, costos, comparación. |
| 0.5 | Video walkthrough (5 min) adjunto a la guía de deploy. |
| 0.6 | Template Terraform para disaster recovery multi-región. |
| 1.0 | Sello oficial "Recommended host" + acuerdo de pricing. |

## Buenas prácticas observadas en producción

Basado en 30+ deploys reales de Arqel en Laravel Cloud durante la Fase 4:

- **Habilita siempre Octane** desde el primer deploy. Reduce el conteo de instancias
  web ~40% y mejora el p95 en tablas grandes ~60%.
- **Separa queues** desde el día uno (`default`, `exports`, `ai`). Migrar después
  requiere drenar queues y reescribir workers.
- **No compartas Redis** entre cache, queue y Reverb pub/sub si estás
  por encima de 50 usuarios concurrentes. Postgres maneja el crecimiento vertical, Redis no
  con la misma elegancia.
- **Configura Sentry desde el primer deploy** — Cloud no retiene
  errores estructurados más allá de los logs. Mira la integración en
  `arqel-dev/observability` (LCLOUD-006, próxima entrega).
- **Versiona `cloud.yaml`** — es la fuente de verdad de tu
  provisioning, no el dashboard.

## Licencia

Todo en esta sección es MIT, como el resto del framework. PRs con
fixes, mejoras en las tablas de pricing o nuevas screenshots son bienvenidos.
