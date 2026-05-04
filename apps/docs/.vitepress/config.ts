import { defineConfig } from 'vitepress';

const ogImage = '/og.png';

export default defineConfig({
  title: 'Arqel',
  titleTemplate: ':title — Arqel',
  description: 'Admin panels for Laravel, forged in PHP, rendered in React.',
  lang: 'pt-BR',
  cleanUrls: true,
  lastUpdated: true,
  srcExclude: ['**/SKILL.md', '**/README.md'],
  // Phase 2 guides reference packages/*/SKILL.md and PLANNING/*.md which
  // live outside the site root — VitePress can't resolve them but they
  // are valid GitHub paths (rendered correctly when viewing the source
  // on github.com). Mark them as known-external so the build doesn't fail.
  ignoreDeadLinks: [
    /\/\.\.\/\.\.\/packages\//,
    /\/\.\.\/\.\.\/PLANNING\//,
    /\/README$/,
    /^\.\.\/\.\.\/packages\//,
    /^\.\.\/\.\.\/PLANNING\//,
  ],

  head: [
    ['link', { rel: 'icon', href: '/favicon.svg', type: 'image/svg+xml' }],
    ['meta', { name: 'theme-color', content: '#6366f1' }],
    ['meta', { property: 'og:title', content: 'Arqel — Admin panels for Laravel' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:image', content: ogImage }],
    ['meta', { property: 'og:url', content: 'https://arqel.dev' }],
    ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
  ],

  themeConfig: {
    logo: '/logo.svg',
    siteTitle: 'Arqel',

    nav: [
      { text: 'Docs', link: '/guide/getting-started', activeMatch: '/guide/' },
      {
        text: 'Recursos',
        link: '/resources/resource',
        activeMatch: '/resources/',
      },
      { text: 'API', link: '/reference/php-overview', activeMatch: '/reference/' },
      { text: 'Avançado', link: '/advanced/custom-fields', activeMatch: '/advanced/' },
      { text: 'Exemplos', link: '/examples/blog-admin', activeMatch: '/examples/' },
      { text: 'Marketplace', link: '/marketplace/', activeMatch: '/marketplace/' },
      { text: 'Laravel Cloud', link: '/laravel-cloud/', activeMatch: '/laravel-cloud/' },
      {
        text: 'v0.0',
        items: [
          { text: 'Changelog', link: 'https://github.com/arqel-dev/arqel/blob/main/CHANGELOG.md' },
          {
            text: 'Roadmap',
            link: 'https://github.com/arqel-dev/arqel/blob/main/PLANNING/07-roadmap-fases.md',
          },
        ],
      },
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Começando',
          items: [
            { text: 'O que é Arqel?', link: '/guide/what-is-arqel' },
            { text: 'Getting Started', link: '/guide/getting-started' },
            { text: 'Instalação', link: '/guide/installation' },
          ],
        },
        {
          text: 'Conceitos',
          items: [
            { text: 'Panels', link: '/guide/panels' },
            { text: 'Resources', link: '/guide/resources' },
            { text: 'Fields', link: '/guide/fields' },
            { text: 'Tables & Forms', link: '/guide/tables-forms' },
            { text: 'Actions', link: '/guide/actions' },
            { text: 'Authentication (login/registro)', link: '/guide/authentication' },
            { text: 'Auth (authorization)', link: '/guide/auth' },
          ],
        },
        {
          text: 'Tutorial',
          items: [{ text: 'Primeiro CRUD completo', link: '/guide/tutorial-first-crud' }],
        },
        {
          text: 'Realtime',
          items: [{ text: 'Edição colaborativa (Yjs + Reverb)', link: '/guide/realtime-collab' }],
        },
        {
          text: 'Integrações',
          items: [{ text: 'AGENTS.md (LLMs)', link: '/guide/agents' }],
        },
        {
          text: 'Acessibilidade',
          items: [{ text: 'Guia a11y (WCAG 2.1 AA)', link: '/guide/a11y' }],
        },
        {
          text: 'Release',
          items: [{ text: 'Release checklist', link: '/guide/release-checklist' }],
        },
        {
          text: 'Comunidade',
          items: [{ text: 'Contribuir', link: '/guide/contributing' }],
        },
        {
          text: 'Migração',
          items: [
            { text: 'Vindo de Filament', link: '/guide/migration/from-filament' },
            { text: 'Vindo de Nova', link: '/guide/migration/from-nova' },
            { text: 'Vindo de react-admin', link: '/guide/migration/from-react-admin' },
          ],
        },
      ],
      '/resources/': [
        {
          text: 'Recursos',
          items: [
            { text: 'Resource', link: '/resources/resource' },
            { text: 'Fields', link: '/resources/fields' },
            { text: 'Table', link: '/resources/table' },
            { text: 'Form', link: '/resources/form' },
            { text: 'Actions', link: '/resources/actions' },
          ],
        },
      ],
      '/reference/': [
        {
          text: 'PHP',
          items: [
            { text: 'Overview', link: '/reference/php-overview' },
            { text: 'arqel-dev/core', link: '/reference/php/core' },
            { text: 'arqel-dev/fields', link: '/reference/php/fields' },
            { text: 'arqel-dev/table', link: '/reference/php/table' },
            { text: 'arqel-dev/form', link: '/reference/php/form' },
            { text: 'arqel-dev/actions', link: '/reference/php/actions' },
            { text: 'arqel-dev/auth', link: '/reference/php/auth' },
            { text: 'arqel-dev/nav', link: '/reference/php/nav' },
          ],
        },
        {
          text: 'TypeScript',
          items: [
            { text: 'Overview', link: '/reference/typescript-overview' },
            { text: '@arqel-dev/types', link: '/reference/typescript/types' },
            { text: '@arqel-dev/react', link: '/reference/typescript/react' },
            { text: '@arqel-dev/hooks', link: '/reference/typescript/hooks' },
            { text: '@arqel-dev/ui', link: '/reference/typescript/ui' },
            { text: '@arqel-dev/fields', link: '/reference/typescript/fields' },
          ],
        },
      ],
      '/advanced/': [
        {
          text: 'Avançado',
          items: [
            { text: 'Custom Fields', link: '/advanced/custom-fields' },
            { text: 'Macros', link: '/advanced/macros' },
          ],
        },
        {
          text: 'Phase 2 features',
          items: [
            { text: 'Multi-tenancy', link: '/advanced/multi-tenancy' },
            { text: 'Dashboards & Widgets', link: '/advanced/dashboards' },
            { text: 'Tables V2 enhancements', link: '/advanced/tables-v2' },
            { text: 'MCP server', link: '/advanced/mcp' },
            { text: 'Command palette', link: '/advanced/command-palette' },
          ],
        },
        {
          text: 'Ferramentas',
          items: [{ text: 'DevTools extension (install)', link: '/devtools-extension/install' }],
        },
      ],
      '/examples/': [
        {
          text: 'Exemplos',
          items: [
            { text: 'Blog admin', link: '/examples/blog-admin' },
            { text: 'Demo app (showcase)', link: '/examples/demo-app' },
          ],
        },
        {
          text: 'Workflows',
          items: [
            { text: 'Visão geral', link: '/examples/workflows/' },
            { text: 'Order states', link: '/examples/workflows/order-states' },
            { text: 'Article states', link: '/examples/workflows/article-states' },
            { text: 'Subscription states', link: '/examples/workflows/subscription-states' },
          ],
        },
        {
          text: 'Versioning',
          items: [
            { text: 'Visão geral', link: '/examples/versioning/' },
            { text: 'CMS articles', link: '/examples/versioning/cms-articles' },
            { text: 'E-commerce orders', link: '/examples/versioning/ecommerce-orders' },
            { text: 'Legal contracts', link: '/examples/versioning/legal-contracts' },
          ],
        },
      ],
      '/marketplace/': [
        {
          text: 'Marketplace',
          items: [
            { text: 'Visão geral', link: '/marketplace/' },
            { text: 'Encontrando plugins', link: '/marketplace/finding-plugins' },
            { text: 'Publicando plugins', link: '/marketplace/publishing' },
            { text: 'Tutorial de desenvolvimento', link: '/marketplace/development-tutorial' },
            { text: 'Boas práticas de segurança', link: '/marketplace/security-best-practices' },
            { text: 'Pagamentos & licenças', link: '/marketplace/payments-and-licensing' },
          ],
        },
      ],
      '/laravel-cloud/': [
        {
          text: 'Laravel Cloud',
          items: [
            { text: 'Visão geral', link: '/laravel-cloud/' },
            { text: 'Deploy guide', link: '/laravel-cloud/deploy-guide' },
            { text: 'Auto-scaling', link: '/laravel-cloud/auto-scaling' },
            { text: 'Estimativa de custos', link: '/laravel-cloud/cost-estimation' },
            { text: 'Comparação com outros hosts', link: '/laravel-cloud/comparison-other-hosts' },
          ],
        },
      ],
    },

    socialLinks: [{ icon: 'github', link: 'https://github.com/arqel-dev/arqel' }],

    editLink: {
      pattern: 'https://github.com/arqel-dev/arqel/edit/main/apps/docs/:path',
      text: 'Sugerir edição nesta página',
    },

    footer: {
      message: 'MIT License — built with Inertia + React + Laravel.',
      copyright: 'Copyright © 2026 Arqel contributors',
    },

    search: {
      provider: 'local',
      options: {
        locales: {
          root: {
            translations: {
              button: { buttonText: 'Buscar', buttonAriaLabel: 'Buscar' },
              modal: {
                noResultsText: 'Sem resultados para',
                resetButtonTitle: 'Limpar busca',
                footer: {
                  selectText: 'selecionar',
                  navigateText: 'navegar',
                  closeText: 'fechar',
                },
              },
            },
          },
        },
      },
    },

    outline: { label: 'Nesta página' },
    docFooter: { prev: 'Anterior', next: 'Próximo' },
    darkModeSwitchLabel: 'Tema',
    sidebarMenuLabel: 'Menu',
    returnToTopLabel: 'Voltar ao topo',
    langMenuLabel: 'Idioma',
    notFound: {
      title: 'Página não encontrada',
      quote: 'Esta página foi movida ou nunca existiu.',
      linkText: 'Voltar ao início',
    },
  },
});
