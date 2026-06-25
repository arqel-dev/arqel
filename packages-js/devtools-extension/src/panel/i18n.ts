/**
 * Self-contained i18n for the Arqel DevTools panel.
 *
 * This package is a browser DevTools extension: it has NO Inertia page
 * context, so `useArqelTranslations()` / `usePage()` from
 * `@arqel-dev/react` are unavailable here (there is no i18n prop, and a
 * dependency on `@arqel-dev/react` would also introduce a cycle). Instead
 * we derive the active locale locally from `navigator.language` and look
 * up flat `devtools.*` keys in the bundled dictionaries below.
 *
 * The English values are the canonical literals: missing keys fall back to
 * the provided English fallback (or the key itself), so accessible names
 * and existing tests stay stable. Supports `:placeholder` interpolation.
 */

type Locale = 'en' | 'pt_BR';

type Dictionary = Readonly<Record<string, string>>;

const EN: Dictionary = {
  'devtools.tab.inertia': 'Inertia State Inspector',
  'devtools.tab.policies': 'Policies',
  'devtools.tab.fields': 'Fields',
  'devtools.tab.time_travel': 'Time Travel',
  'devtools.tab.performance': 'Performance',
  'devtools.inactive_hint': 'Open a page running an Arqel admin panel to activate DevTools.',
  'devtools.filter_placeholder': 'Filter…',
  'devtools.inertia.filter_aria': 'Filter inspector',
  'devtools.inertia.copy_aria': 'Copy state JSON',
  'devtools.inertia.tab.page_props': 'Page Props',
  'devtools.inertia.tab.shared_props': 'Shared Props',
  'devtools.inertia.tab.navigation_history': 'Navigation History',
  'devtools.inertia.copy': 'Copy',
  'devtools.inertia.copied': 'Copied!',
  'devtools.inertia.empty.no_navigation': 'No navigation events recorded.',
  'devtools.policy.filter_placeholder': 'Filter ability…',
  'devtools.policy.filter_ability_aria': 'Filter ability',
  'devtools.policy.filter_result_aria': 'Filter by result',
  'devtools.policy.filter.all': 'All',
  'devtools.policy.filter.allow': 'Allowed',
  'devtools.policy.filter.deny': 'Denied',
  'devtools.policy.col.ability': 'Ability',
  'devtools.policy.col.arguments': 'Arguments',
  'devtools.policy.col.result': 'Result',
  'devtools.policy.col.stack': 'Stack',
  'devtools.policy.status.allow': 'allow',
  'devtools.policy.status.deny': 'deny',
  'devtools.policy.counter': ':allow allowed / :deny denied',
  'devtools.fields.counter': ':visible visible / :total total',
  'devtools.fields.filter_placeholder': 'Filter name…',
  'devtools.fields.filter_name_aria': 'Filter field name',
  'devtools.fields.filter_type_aria': 'Filter by type',
  'devtools.fields.all_types': 'All types',
  'devtools.fields.heading.validation': 'Validation rules',
  'devtools.fields.heading.depends_on': 'Depends on',
  'devtools.fields.heading.visibility': 'Visibility rule',
  'devtools.fields.heading.meta': 'Meta',
  'devtools.fields.no_rules': 'No validation rules.',
  'devtools.fields.empty.no_fields': 'No fields detected in the current pageProps.',
  'devtools.fields.empty.no_match': 'No fields match the current filter.',
  'devtools.policy.empty.no_checks': 'No policy checks recorded for this request.',
  'devtools.policy.empty.no_match': 'No entries match the current filter.',
  'devtools.policy.stack.show': 'Show',
  'devtools.policy.stack.hide': 'Hide',
  'devtools.performance.empty':
    'No performance metrics captured yet. Interact with the page to populate Web Vitals.',
  'devtools.performance.label.navigation': 'Navigation',
  'devtools.performance.hint.lcp': 'Largest Contentful Paint',
  'devtools.performance.hint.responsiveness': 'Interaction latency',
  'devtools.performance.hint.cls': 'Cumulative Layout Shift',
  'devtools.performance.hint.navigation': 'Initial navigation duration',
  'devtools.performance.server.queries': 'Queries',
  'devtools.performance.server.memory': 'Memory',
  'devtools.time_travel.replay': 'Replay',
  'devtools.time_travel.replay_aria': 'Replay :url',
  'devtools.time_travel.snapshot_count_one': ':count snapshot',
  'devtools.time_travel.snapshot_count_other': ':count snapshots',
  'devtools.time_travel.empty.no_snapshots':
    'No navigation snapshots captured yet. Navigate within the app to record state.',
};

const PT_BR: Dictionary = {
  'devtools.tab.inertia': 'Inspetor de Estado Inertia',
  'devtools.tab.policies': 'Políticas',
  'devtools.tab.fields': 'Campos',
  'devtools.tab.time_travel': 'Viagem no Tempo',
  'devtools.tab.performance': 'Desempenho',
  'devtools.inactive_hint':
    'Abra uma página executando um painel admin Arqel para ativar o DevTools.',
  'devtools.filter_placeholder': 'Filtrar…',
  'devtools.inertia.filter_aria': 'Filtrar inspetor',
  'devtools.inertia.copy_aria': 'Copiar JSON de estado',
  'devtools.inertia.tab.page_props': 'Props da Página',
  'devtools.inertia.tab.shared_props': 'Props Compartilhadas',
  'devtools.inertia.tab.navigation_history': 'Histórico de Navegação',
  'devtools.inertia.copy': 'Copiar',
  'devtools.inertia.copied': 'Copiado!',
  'devtools.inertia.empty.no_navigation': 'Nenhum evento de navegação registrado.',
  'devtools.policy.filter_placeholder': 'Filtrar habilidade…',
  'devtools.policy.filter_ability_aria': 'Filtrar habilidade',
  'devtools.policy.filter_result_aria': 'Filtrar por resultado',
  'devtools.policy.filter.all': 'Todos',
  'devtools.policy.filter.allow': 'Permitido',
  'devtools.policy.filter.deny': 'Negado',
  'devtools.policy.col.ability': 'Habilidade',
  'devtools.policy.col.arguments': 'Argumentos',
  'devtools.policy.col.result': 'Resultado',
  'devtools.policy.col.stack': 'Pilha',
  'devtools.policy.status.allow': 'permitido',
  'devtools.policy.status.deny': 'negado',
  'devtools.policy.counter': ':allow permitidos / :deny negados',
  'devtools.fields.counter': ':visible visíveis / :total no total',
  'devtools.fields.filter_placeholder': 'Filtrar nome…',
  'devtools.fields.filter_name_aria': 'Filtrar nome do campo',
  'devtools.fields.filter_type_aria': 'Filtrar por tipo',
  'devtools.fields.all_types': 'Todos os tipos',
  'devtools.fields.heading.validation': 'Regras de validação',
  'devtools.fields.heading.depends_on': 'Depende de',
  'devtools.fields.heading.visibility': 'Regra de visibilidade',
  'devtools.fields.heading.meta': 'Meta',
  'devtools.fields.no_rules': 'Nenhuma regra de validação.',
  'devtools.fields.empty.no_fields': 'Nenhum campo detectado nas pageProps atuais.',
  'devtools.fields.empty.no_match': 'Nenhum campo corresponde ao filtro atual.',
  'devtools.policy.empty.no_checks':
    'Nenhuma verificação de política registrada para esta requisição.',
  'devtools.policy.empty.no_match': 'Nenhuma entrada corresponde ao filtro atual.',
  'devtools.policy.stack.show': 'Mostrar',
  'devtools.policy.stack.hide': 'Ocultar',
  'devtools.performance.empty':
    'Nenhuma métrica de desempenho capturada ainda. Interaja com a página para popular os Web Vitals.',
  'devtools.performance.label.navigation': 'Navegação',
  'devtools.performance.hint.lcp': 'Maior Conteúdo Renderizado',
  'devtools.performance.hint.responsiveness': 'Latência de interação',
  'devtools.performance.hint.cls': 'Mudança Cumulativa de Layout',
  'devtools.performance.hint.navigation': 'Duração da navegação inicial',
  'devtools.performance.server.queries': 'Consultas',
  'devtools.performance.server.memory': 'Memória',
  'devtools.time_travel.replay': 'Reproduzir',
  'devtools.time_travel.replay_aria': 'Reproduzir :url',
  'devtools.time_travel.snapshot_count_one': ':count snapshot',
  'devtools.time_travel.snapshot_count_other': ':count snapshots',
  'devtools.time_travel.empty.no_snapshots':
    'Nenhum snapshot de navegação capturado ainda. Navegue pelo aplicativo para registrar o estado.',
};

const DICTIONARIES: Readonly<Record<Locale, Dictionary>> = {
  en: EN,
  pt_BR: PT_BR,
};

/**
 * Normalize a BCP-47-ish `navigator.language` tag to a supported locale.
 * Maps `pt`, `pt-BR`, `pt_br` (any casing) to `pt_BR`; everything else
 * falls back to `en`.
 */
export function normalizeLocale(tag: string | null | undefined): Locale {
  if (typeof tag !== 'string' || tag === '') return 'en';
  const lower = tag.toLowerCase().replace('_', '-');
  if (lower === 'pt' || lower.startsWith('pt-')) return 'pt_BR';
  return 'en';
}

function activeLocale(): Locale {
  if (typeof navigator !== 'undefined') {
    return normalizeLocale(navigator.language);
  }
  return 'en';
}

const BCP47_TAGS: Readonly<Record<Locale, string>> = {
  en: 'en',
  pt_BR: 'pt-BR',
};

/**
 * BCP-47 tag for the active locale, suitable for `Intl.*` / `toLocale*`
 * APIs. Derived from `navigator.language` via the same normalization used
 * for translations, so e.g. `pt-BR` browsers get `pt-BR` (24h) timestamps.
 */
export function localeBcp47(): string {
  return BCP47_TAGS[activeLocale()];
}

function interpolate(value: string, replacements?: Record<string, string | number>): string {
  if (!replacements) return value;
  return value.replace(/:(\w+)/g, (match, name: string) => {
    const replacement = replacements[name];
    return replacement === undefined ? match : String(replacement);
  });
}

/**
 * Translate a flat `devtools.*` key for the active `navigator.language`
 * locale. Returns the `fallback` (or the key when no fallback is given)
 * if the key is missing, and applies `:placeholder` interpolation.
 */
export function t(
  key: string,
  fallback?: string,
  replacements?: Record<string, string | number>,
): string {
  const locale = activeLocale();
  const localized = DICTIONARIES[locale][key] ?? EN[key] ?? fallback ?? key;
  return interpolate(localized, replacements);
}
