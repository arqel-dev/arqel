# SecurityScanner Version Matching — Design

**Data:** 2026-07-07
**Tipo:** fix (segurança/correção) • **Pacote:** `arqel/marketplace`
**Origem:** bug-hunt do quality-loop — `Advisory::fixedIn` nunca é lido pelo scanner.

## Contexto

`SecurityScanner::scan()` roda checks sobre um `Plugin` do marketplace e, se acha um
finding `critical` num plugin publicado, **auto-delista** o plugin
(`SecurityScanner.php:94-96` → `PluginAutoDelistedEvent`).

Os findings de vulnerabilidade vêm de `lookupVulnerabilities()`, que itera os advisories
retornados por `VulnerabilityDatabase::lookup($package, $ecosystem)` e transforma **cada
um** num finding. O value-object `Advisory` carrega um campo `fixedIn` (obrigatório no
construtor) — mas o scanner **nunca o lê**. É dead data.

**Bug:** o scanner não compara a versão instalada do plugin (`Plugin::$latest_version`)
com o range de versões afetadas do advisory. Consequência: um plugin que já atualizou para
uma versão corrigida é marcado vulnerável e, se o advisory é `critical`, **auto-delistado**
— um falso positivo com efeito destrutivo (remove do marketplace um plugin seguro).

Evidência de que o campo deveria filtrar por versão:

- `Advisory::fixedIn` é um parâmetro obrigatório — sua presença é uma promessa de matching.
- Os testes constroem advisories com valores tipo `'>=1.0.1'`, `'>=2'`, `'>=2.0.0'` — sintaxe
  de constraint Composer, não uma versão única.
- Os testes atuais passam por coincidência: `makeScanPlugin` não seta `latest_version`
  (fica `null`), então nenhum matching de versão é exercitado — todo advisory vira finding.

## Decisões (do brainstorming)

1. **Semântica do campo = "versões afetadas"** (não "fixed in"). Rename
   `Advisory::fixedIn` → `Advisory::affectedVersions`, uma constraint Composer das versões
   vulneráveis (ex: `'<2.0'`, `'>=1.0.1,<1.5'`). Modelo do GitHub Advisory / OSV. Rename
   seguro: `Advisory` é `final readonly` novo, consumido só pelo scanner + fakes de teste.
2. **Matching via `Composer\Semver\Semver::satisfies`** — já presente transitivamente no
   vendor; adicionado como `require` explícito do marketplace (`composer/semver: ^3.0`).
   Sintaxe idêntica ao `composer.json`. Não é dependência nova no lock.
3. **Lógica isolada num `VersionMatcher`** testável, com fail-safes de segurança.

## Arquitetura

### Artefatos

| Artefato | Mudança |
|---|---|
| `src/Contracts/Advisory.php` | `fixedIn` → `affectedVersions` (+ docblock) |
| `src/Services/VersionMatcher.php` | **novo** — `isAffected(?string, string): bool` |
| `src/Services/SecurityScanner.php` | `lookupVulnerabilities` filtra por `VersionMatcher::isAffected` antes de materializar o finding |
| `composer.json` (marketplace) | `require` explícito `composer/semver: ^3.0` |

O contrato `VulnerabilityDatabase::lookup(package, ecosystem)` **não muda** (external-facing:
implementações reais como GitHub Advisory devolvem o range; filtrar é responsabilidade do
consumidor). `rollupSeverity` / `statusFor` / `normalizeSeverity` (o fix #352) e o
auto-delist **não mudam**.

### `VersionMatcher`

```php
final class VersionMatcher
{
    /**
     * True if the installed version falls within the advisory's
     * affected-version constraint. Fails safe to `true` (affected):
     * an unknown installed version or an unparseable constraint must
     * never let a plugin escape the scanner.
     */
    public static function isAffected(?string $installed, string $affectedConstraint): bool
    {
        if ($installed === null || trim($installed) === '') {
            return true; // versão desconhecida → não dá pra provar seguro → afetado
        }

        if (trim($affectedConstraint) === '') {
            return true; // sem range declarado → todas as versões afetadas
        }

        try {
            return Semver::satisfies($installed, $affectedConstraint);
        } catch (\UnexpectedValueException | \InvalidArgumentException) {
            return true; // versão ou constraint inválida → fail safe (afetado)
        }
    }
}
```

**Três fail-safes, todos para "afetado"** (princípio do #352: o desconhecido flagga, nunca
passa). O `VersionParser` do composer/semver lança **duas** classes para entradas inválidas:
`UnexpectedValueException` (versão/constraint malformada — `VersionParser.php:191/526`) e
`InvalidArgumentException` (stability string inválida — `VersionParser.php:92`). Capturamos
ambas — específicas, para não mascarar erros de programação, mas cobrindo toda entrada
inválida que o parser pode rejeitar. **Não** capturamos `\Throwable` genérico.

### Integração no scanner

`lookupVulnerabilities` ganha o filtro antes de materializar o finding, idêntico nos dois
ramos (composer + npm):

```php
foreach ($this->vulnDb->lookup($plugin->composer_package, 'composer') as $advisory) {
    if (! VersionMatcher::isAffected($plugin->latest_version, $advisory->affectedVersions)) {
        continue; // plugin já numa versão não-afetada — não é vulnerável
    }
    $findings[] = [ /* ...igual ao atual... */ ];
}
```

O resto do `scan()` fica intacto. Findings agora só existem para versões genuinamente
afetadas, então o auto-delist só dispara para plugins realmente vulneráveis.

## Segurança

- **Fail-safe sempre para "afetado"** — versão null/vazia, constraint vazia, ou entrada
  inválida nunca deixam um plugin escapar do scanner. Um erro de dados flagga (revisão
  humana), nunca silenciosamente aprova.
- **Sem SQL/eval** — `Semver::satisfies` é comparação pura de strings de versão.
- **Escopo contido** — não toca auth/permissions/crypto/secrets; só a lógica de comparação
  de versão que decide se um finding é materializado.

## Erros & edge cases

| Caso | Comportamento |
|---|---|
| `latest_version` null/vazio | afetado (flagga) |
| `affectedVersions` vazio | afetado (todas versões) |
| `affectedVersions` inválido (semver lança) | afetado (fail-safe) |
| `latest_version` inválido | afetado (fail-safe) |
| plugin em versão acima do range | **não** afetado → sem finding (o bug corrigido) |
| plugin dentro do range | afetado → finding com a severity do advisory |
| múltiplos advisories, alguns aplicáveis | só os aplicáveis viram findings; `rollupSeverity` inalterado |

## Testes (Pest — `packages/marketplace/vendor/bin/pest`)

**Novo `VersionMatcherTest` (unit, 100% da unidade crítica):**

- `'1.0.0'` vs `'<2.0'` → true
- `'2.5.0'` vs `'<2.0'` → false (plugin já corrigido)
- `'1.3.0'` vs `'>=1.0.1,<1.5'` → true
- `'1.6.0'` vs `'>=1.0.1,<1.5'` → false
- `null` vs `'<2.0'` → true (fail-safe)
- `''` vs `'<2.0'` → true (fail-safe)
- `'1.0.0'` vs `''` → true (fail-safe)
- `'not-a-version'` vs `'<2.0'` → true (fail-safe)
- `'1.0.0'` vs `'garbage!!'` → true (fail-safe — `UnexpectedValueException`)
- `'1.0.0'` vs `'>=1.0.0@badstability'` → true (fail-safe — `InvalidArgumentException` do parser de stability)

**`SecurityScannerTest` — atualizar + estender:**

- Existentes com `Advisory(..., '>=X')` + `makeScanPlugin` (versão null) continuam passando
  (null → afetado). Args são posicionais, então o rename não quebra chamadas.
- **Novo (prova do bug):** plugin `latest_version: '2.5.0'` + advisory `critical`
  `affectedVersions: '<2.0'` → `status: passed`, `findings: []`, **não** auto-delistado.
  Este teste **falha na baseline** e passa após o fix (demonstração TDD).
- **Novo (range):** plugin `latest_version: '1.3.0'` + advisory `critical`
  `affectedVersions: '>=1.0,<1.5'` → `failed` + auto-delistado (genuinamente vulnerável).
- **Novo (fail-safe no scanner):** plugin `latest_version: null` + advisory
  `affectedVersions: '<2.0'` → ainda flaggeado.

Cobertura-alvo: marketplace core ≥90%.

## Fora de escopo

- Integração real com GitHub Advisory / OSV (a `StaticVulnerabilityDatabase` continua um
  stub vazio — MKTPLC-009 futuro).
- Análise estática de código do plugin (TODO já marcado no scanner).
- Mudança no contrato `VulnerabilityDatabase::lookup`.
