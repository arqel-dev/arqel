/**
 * Self-contained i18n for the Arqel Ink TUI.
 *
 * The CLI runs in a terminal with no Inertia/browser context, so it cannot use
 * `useArqelTranslations` from `@arqel-dev/react`. This module provides a tiny,
 * dependency-free `t()` with flat `cli.*` dictionaries. The active locale is
 * derived from the environment (`LC_ALL` / `LC_MESSAGES` / `LANG`), e.g.
 * `pt_BR.UTF-8` -> `pt_BR`, defaulting to `en`.
 */

export type CliLocale = 'en' | 'pt_BR';

type Replacements = Record<string, string | number>;

const en: Record<string, string> = {
  'cli.menu.title': 'Arqel — Terminal UI',
  'cli.menu.hint': 'Use arrows to navigate, enter to select, q to quit.',
  'cli.menu.dashboard': 'Dashboard',
  'cli.menu.resources': 'Resources',
  'cli.menu.logs': 'Logs',
  'cli.menu.quit': 'Quit',
  'cli.dashboard.title': 'Arqel Dashboard',
  'cli.dashboard.loading': 'Loading dashboard…',
  'cli.dashboard.empty': 'No data available.',
  'cli.dashboard.tile.queries_per_sec': 'Queries / sec',
  'cli.dashboard.tile.active_users': 'Active users',
  'cli.dashboard.tile.errors_5m': 'Errors (5m)',
  'cli.dashboard.tile.ai_tokens': 'AI tokens',
  'cli.resources.loading': 'Loading resources…',
  'cli.resources.empty': 'No resources found.',
  'cli.resources.heading': 'Resources',
  'cli.resources.slug': 'slug:',
  'cli.resources.records': 'Records:',
  'cli.logs.header': 'Log:',
  'cli.logs.following': '(following)',
  'cli.logs.file_not_found': 'File not found: ',
  'cli.error.prefix': 'Error:',
  'cli.error.logs_requires_file': '`logs` requires a file path. Try: arqel-ink logs <file>',
};

const pt_BR: Record<string, string> = {
  'cli.menu.title': 'Arqel — Interface de Terminal',
  'cli.menu.hint': 'Use as setas para navegar, enter para selecionar, q para sair.',
  'cli.menu.dashboard': 'Painel',
  'cli.menu.resources': 'Recursos',
  'cli.menu.logs': 'Logs',
  'cli.menu.quit': 'Sair',
  'cli.dashboard.title': 'Painel Arqel',
  'cli.dashboard.loading': 'Carregando painel…',
  'cli.dashboard.empty': 'Nenhum dado disponível.',
  'cli.dashboard.tile.queries_per_sec': 'Consultas / s',
  'cli.dashboard.tile.active_users': 'Usuários ativos',
  'cli.dashboard.tile.errors_5m': 'Erros (5m)',
  'cli.dashboard.tile.ai_tokens': 'Tokens de IA',
  'cli.resources.loading': 'Carregando recursos…',
  'cli.resources.empty': 'Nenhum recurso encontrado.',
  'cli.resources.heading': 'Recursos',
  'cli.resources.slug': 'slug:',
  'cli.resources.records': 'Registros:',
  'cli.logs.header': 'Log:',
  'cli.logs.following': '(acompanhando)',
  'cli.logs.file_not_found': 'Arquivo não encontrado: ',
  'cli.error.prefix': 'Erro:',
  'cli.error.logs_requires_file':
    '`logs` requer o caminho de um arquivo. Tente: arqel-ink logs <file>',
};

const dictionaries: Record<CliLocale, Record<string, string>> = { en, pt_BR };

/**
 * Resolve the active locale from the process environment.
 * Reads `LC_ALL`, then `LC_MESSAGES`, then `LANG`; strips the encoding/country
 * suffix (e.g. `pt_BR.UTF-8` -> `pt_BR`). Defaults to `en`.
 */
export function resolveLocale(env: NodeJS.ProcessEnv = process.env): CliLocale {
  const raw = env['LC_ALL'] || env['LC_MESSAGES'] || env['LANG'] || '';
  const tag = raw.split('.')[0]?.trim() ?? '';
  if (tag === 'pt_BR' || tag === 'pt-BR') return 'pt_BR';
  return 'en';
}

function interpolate(template: string, replacements?: Replacements): string {
  if (!replacements) return template;
  return template.replace(/:([a-zA-Z_]+)/g, (match, name: string) => {
    const value = replacements[name];
    return value === undefined ? match : String(value);
  });
}

/**
 * Translate a flat `cli.*` key for the active environment locale.
 *
 * Resolution order: active-locale value -> English value -> provided
 * `fallback` -> the key itself. Supports `:placeholder` interpolation.
 */
export function t(key: string, fallback?: string, replacements?: Replacements): string {
  const locale = resolveLocale();
  const localized = dictionaries[locale]?.[key];
  const template = localized ?? en[key] ?? fallback ?? key;
  return interpolate(template, replacements);
}

export const dictionariesByLocale = dictionaries;
