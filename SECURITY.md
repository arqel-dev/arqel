# Política de segurança

## Versões suportadas

Durante a Fase 1 (MVP), **nenhuma versão** é considerada estável. Uso em produção não é recomendado.

A partir de `v1.0` (Fase 3), as duas últimas versões minor receberão patches de segurança.

| Versão | Suportada |
| --- | --- |
| `0.x` (pre-release) | ⚠️ Melhor esforço, sem garantias |
| `1.x` (futuro) | ✅ A definir |

## Reportar uma vulnerabilidade

**NÃO abra uma issue pública para vulnerabilidades de segurança.**

Envie o relatório para **security@arqel.dev** ou use o [GitHub Security Advisories](https://github.com/arqel/arqel/security/advisories/new) (recomendado — permite colaboração privada).

### O que incluir

- Descrição da vulnerabilidade e possível impacto
- Passos para reproduzir (proof-of-concept idealmente)
- Versão afectada e ambiente (PHP, Laravel, SO)
- Possível mitigação, se conhecida

### O que esperar

1. **Confirmação de receção** dentro de 48h
2. **Triagem inicial** dentro de 7 dias (severidade, âmbito)
3. **Correção coordenada** em janela acordada com quem reporta
4. **Divulgação pública** após patch lançado (via GitHub Security Advisory + CVE quando aplicável)
5. **Crédito** a quem reporta no advisory, salvo indicação em contrário

## Âmbito

**Dentro do âmbito:**

- Packages `arqel/*` publicados no Packagist
- Packages `@arqel/*` publicados no npm
- Código neste repositório (`github.com/arqel/arqel`)

**Fora do âmbito:**

- Vulnerabilidades em Laravel, Inertia, React (reportar aos projetos originais)
- Vulnerabilidades em dependências terceiras (reportar ao vendor, podemos ajudar a coordenar)
- Apps consumidoras configuradas incorretamente (ex: policies em falta)
- Social engineering ou ataques físicos

## Boas práticas para quem integra o Arqel

- Manter todas as dependências actualizadas via `composer update` e `pnpm update` regulares
- Seguir as [Laravel Security Best Practices](https://laravel.com/docs/security)
- Habilitar CSP headers no painel admin
- Usar HTTPS em produção
- Policies são obrigatórias em todos os Resources (Arqel falha fechado por default)

---

**Última actualização:** 2026-04-17

> **Nota:** esta política será expandida no ticket GOV-001. Este ficheiro é um placeholder funcional criado em INFRA-001 para satisfazer os critérios de aceite mínimos.
