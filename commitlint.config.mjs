import configConventional from '@commitlint/config-conventional';

// Tipos Conventional Commits permitidos no projecto (alinhado com CLAUDE.md §Commits)
const types = ['feat', 'fix', 'docs', 'style', 'refactor', 'perf', 'test', 'build', 'ci', 'chore'];

// Scopes válidos — nomes de packages + áreas transversais (ver PLANNING/00-index.md §IDs)
const scopes = [
  // Composer packages
  'core',
  'panel',
  'fields',
  'table',
  'form',
  'actions',
  'auth',
  'cli',
  'nav',
  'tenant',
  'ai',
  'widgets',
  'fields-advanced',
  'audit',
  'export',
  'versioning',
  'workflow',
  'realtime',
  'mcp',
  'testing',
  // npm packages
  'react',
  'ui',
  'hooks',
  'types',
  // transversais
  'infra',
  'gov',
  'docs',
  'demo',
  'qa',
  'tickets',
  'sprint',
  'deps',
  'release',
];

export default {
  extends: ['@commitlint/config-conventional'],
  rules: {
    ...configConventional.rules,
    'type-enum': [2, 'always', types],
    'scope-enum': [2, 'always', scopes],
    'scope-empty': [0], // scope é opcional (ex: `chore: release v0.1`)
    'subject-case': [2, 'never', ['start-case', 'pascal-case', 'upper-case']],
    'subject-full-stop': [2, 'never', '.'],
    'header-max-length': [2, 'always', 100],
    'body-max-line-length': [0], // permitir linhas longas em URLs/traces
  },
};
