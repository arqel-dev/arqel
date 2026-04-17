# Política de segurança

A equipa Arqel agradece **reports responsáveis** de vulnerabilidades. Este documento descreve como reportar e o que esperar do processo.

## Versões suportadas

A partir de `v1.0` (Fase 3), as **duas últimas versões minor** recebem patches de segurança.

Durante a Fase 1 (pré-`v1.0`), o suporte é **melhor esforço**: reagimos rapidamente a issues críticos mas não há garantia formal de LTS.

| Versão | Estado | Suporte |
|---|---|---|
| `0.x` (pre-release, Fase 1 e 2) | ⚠️ Beta | Best-effort |
| `1.x.*` (último minor após `v1.0`) | ✅ Activa | Patches críticos e não-críticos |
| `1.(x-1).*` | ✅ Manutenção | Só patches de segurança |
| `1.(x-2).*` e anteriores | ❌ EOL | Sem patches |

Recomendamos sempre actualizar para a `latest` minor.

## Reportar uma vulnerabilidade

**NÃO abra uma issue pública para vulnerabilidades de segurança.**

Canais preferidos, por ordem:

1. **[GitHub Security Advisories](https://github.com/arqel/arqel/security/advisories/new)** (recomendado) — permite colaboração privada e emissão coordenada de CVE.
2. **Email:** `security@arqel.dev` (equipa-core da governance).

### O que incluir no report

- Descrição da vulnerabilidade e **potencial impacto** (RCE, XSS, SQLi, privilege escalation, denial-of-service, etc.)
- **Passos reproduzíveis** — idealmente um proof-of-concept mínimo
- **Versão afectada** (`composer info arqel/core`, `pnpm ls @arqel/ui`) e ambiente (PHP, Laravel, SO, browser)
- **Mitigação** conhecida, se houver
- Identificação (nome ou handle público) para crédito — podes indicar **reporter anónimo** se preferires

## O que esperar

Comprometemo-nos a SLAs escritos abaixo. Se o prazo deslizar, enviamos update explicando.

| Fase | SLA |
|---|---|
| Acknowledgement | **≤ 48 horas** após receção |
| Triage inicial (severidade + âmbito) | ≤ 5 dias úteis |
| Patch para críticas (CVSS ≥ 9.0) | **≤ 14 dias** |
| Patch para altas (CVSS 7.0–8.9) | ≤ 30 dias |
| Patch para médias/baixas | ≤ 90 dias |
| Divulgação pública coordenada | Após patch lançado, CVE pedido |

Severidade é avaliada com **CVSS 3.1**.

## Processo

1. Recebemos o report (GHSA ou email).
2. Confirmamos receção dentro de 48h com um identificador interno (`ARQEL-SEC-YYYY-NNN`).
3. Triage: reproduzimos, confirmamos âmbito, atribuímos severidade.
4. Trabalhamos num patch **em branch privada**. Quem reporta é adicionado ao advisory GHSA e recebe drafts para validação, se aceitar.
5. Publicamos o patch e o advisory simultaneamente. Pedimos CVE via MITRE quando aplicável.
6. Release notes (`CHANGELOG.md` + GitHub Release) referem o advisory.
7. Se a vulnerabilidade afectar consumidores em produção, enviamos **nota pública** no Twitter/GitHub Discussions + email a operadores conhecidos.

## Hall of Fame

Reporters que queiram ser mencionados publicamente entram no [`SECURITY-HALL-OF-FAME.md`](./SECURITY-HALL-OF-FAME.md) (criado quando houver primeira entrada).

Swag enviado se possível (stickers, t-shirt) a reporters de críticas — detalhes via email.

## Âmbito

### Dentro do âmbito

- Packages `arqel/*` publicados no Packagist (todas as versões actuais)
- Packages `@arqel/*` publicados no npm
- Código neste repositório (`github.com/arqel/arqel`)
- Registry ShadCN (`https://arqel.dev/r/*`)
- Website e docs (`https://arqel.dev`, `https://docs.arqel.dev`)

### Fora do âmbito

- Vulnerabilidades em **Laravel**, **Inertia**, **React**, **Tailwind** ou outras dependências upstream — reportar aos projetos originais. Podemos **ajudar a coordenar** se relevante.
- Bugs em **apps consumidoras** causados por configuração incorrecta (ex: Policies em falta, CSRF desabilitado manualmente).
- Social engineering, ataques físicos, phishing a maintainers.
- Spam/abuse em GitHub Discussions — reportar via GitHub Community Guidelines.
- **Denial-of-service via recursos locais** (ex: enviar payload enorme para esgotar RAM da máquina do dev) — tratamos como bug normal.

## Boas práticas para quem integra Arqel

- Manter dependências actualizadas: `composer audit` + `pnpm audit` no CI (ver `.github/workflows/security.yml`)
- Seguir as [Laravel Security Best Practices](https://laravel.com/docs/security)
- Habilitar **CSP headers** no painel admin
- Usar **HTTPS** em produção (sem excepções)
- **Policies obrigatórias em todos os Resources** — Arqel falha fechado por default (ver ADR-017)
- Não expor o painel Arqel a internet pública sem autenticação (`auth` middleware)
- **Secrets em `.env`**, nunca no repo

## Disclosure pública de vulnerabilidades passadas

Advisories publicados vivem em:

- [GitHub Security Advisories do Arqel](https://github.com/arqel/arqel/security/advisories)
- `CHANGELOG.md` (referência curta com link para advisory)

## Chaves criptográficas

Por agora **não** publicamos uma PGP key dedicada — o canal GHSA já provê privacidade. Se necessário, gerimos uma chave em ticket futuro (`GOV-PGP-001`).

---

**Última actualização:** 2026-04-17 (GOV-001)
