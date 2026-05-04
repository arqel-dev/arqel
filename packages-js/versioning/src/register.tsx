/**
 * Side-effect entry para integração futura com FieldRegistry / WidgetRegistry.
 *
 *   import '@arqel-dev/versioning/register';
 *
 * No momento `VersionTimeline` e `VersionDiff` são usados diretamente
 * pelo consumidor (drawer/modal lado-app), sem registro num registry
 * compartilhado. Este arquivo existe como placeholder para que o
 * subpath `@arqel-dev/versioning/register` continue resolvível e para que
 * adições futuras (ex.: registrar o timeline como widget de Resource)
 * tenham um lugar canônico.
 *
 * Re-exporta a API pública para conveniência, mas **não** dispara
 * efeitos colaterais.
 */

export { VersionDiff } from './VersionDiff.js';
export { VersionTimeline } from './VersionTimeline.js';
