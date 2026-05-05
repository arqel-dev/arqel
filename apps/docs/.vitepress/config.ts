import { type DefaultTheme, defineConfig } from 'vitepress';

const ogImage = '/og.png';

type SidebarText = {
  starting: string;
  concepts: string;
  tutorial: string;
  realtime: string;
  integrations: string;
  a11y: string;
  release: string;
  community: string;
  migration: string;
  resources: string;
  reference: string;
  advanced: string;
  examples: string;
  marketplace: string;
  laravelCloud: string;
  navDocs: string;
  navResources: string;
  navApi: string;
  navAdvanced: string;
  navExamples: string;
  navMarketplace: string;
  navLaravelCloud: string;
  whatIs: string;
  gettingStarted: string;
  installation: string;
  panels: string;
  resourcesPage: string;
  fields: string;
  tablesForms: string;
  actions: string;
  authentication: string;
  authorization: string;
  firstCrud: string;
  collab: string;
  agents: string;
  a11yGuide: string;
  releaseChecklist: string;
  contribute: string;
  fromFilament: string;
  fromNova: string;
  fromReactAdmin: string;
  resource: string;
  fieldsRef: string;
  table: string;
  form: string;
  actionsRef: string;
  overview: string;
  customFields: string;
  macros: string;
  phase2: string;
  multiTenancy: string;
  dashboards: string;
  tablesV2: string;
  mcp: string;
  commandPalette: string;
  tools: string;
  devtools: string;
  blogAdmin: string;
  demoApp: string;
  workflows: string;
  generalOverview: string;
  orderStates: string;
  articleStates: string;
  subscriptionStates: string;
  versioning: string;
  cmsArticles: string;
  ecommerceOrders: string;
  legalContracts: string;
  marketplaceOverview: string;
  findingPlugins: string;
  publishing: string;
  developmentTutorial: string;
  securityPractices: string;
  paymentsLicensing: string;
  cloudOverview: string;
  deployGuide: string;
  autoScaling: string;
  costEstimation: string;
  comparison: string;
  versionMenu: string;
  changelog: string;
  roadmap: string;
};

const T = {
  en: {
    starting: 'Getting started',
    concepts: 'Concepts',
    tutorial: 'Tutorial',
    realtime: 'Realtime',
    integrations: 'Integrations',
    a11y: 'Accessibility',
    release: 'Release',
    community: 'Community',
    migration: 'Migration',
    resources: 'Resources',
    reference: 'API reference',
    advanced: 'Advanced',
    examples: 'Examples',
    marketplace: 'Marketplace',
    laravelCloud: 'Laravel Cloud',
    navDocs: 'Docs',
    navResources: 'Resources',
    navApi: 'API',
    navAdvanced: 'Advanced',
    navExamples: 'Examples',
    navMarketplace: 'Marketplace',
    navLaravelCloud: 'Laravel Cloud',
    whatIs: 'What is Arqel?',
    gettingStarted: 'Getting started',
    installation: 'Installation',
    panels: 'Panels',
    resourcesPage: 'Resources',
    fields: 'Fields',
    tablesForms: 'Tables & Forms',
    actions: 'Actions',
    authentication: 'Authentication (login/register)',
    authorization: 'Authorization',
    firstCrud: 'First CRUD walkthrough',
    collab: 'Collaborative editing (Yjs + Reverb)',
    agents: 'AGENTS.md (LLMs)',
    a11yGuide: 'a11y guide (WCAG 2.1 AA)',
    releaseChecklist: 'Release checklist',
    contribute: 'Contributing',
    fromFilament: 'Coming from Filament',
    fromNova: 'Coming from Nova',
    fromReactAdmin: 'Coming from react-admin',
    resource: 'Resource',
    fieldsRef: 'Fields',
    table: 'Table',
    form: 'Form',
    actionsRef: 'Actions',
    overview: 'Overview',
    customFields: 'Custom Fields',
    macros: 'Macros',
    phase2: 'Phase 2 features',
    multiTenancy: 'Multi-tenancy',
    dashboards: 'Dashboards & Widgets',
    tablesV2: 'Tables V2 enhancements',
    mcp: 'MCP server',
    commandPalette: 'Command palette',
    tools: 'Tooling',
    devtools: 'DevTools extension (install)',
    blogAdmin: 'Blog admin',
    demoApp: 'Demo app (showcase)',
    workflows: 'Workflows',
    generalOverview: 'Overview',
    orderStates: 'Order states',
    articleStates: 'Article states',
    subscriptionStates: 'Subscription states',
    versioning: 'Versioning',
    cmsArticles: 'CMS articles',
    ecommerceOrders: 'E-commerce orders',
    legalContracts: 'Legal contracts',
    marketplaceOverview: 'Overview',
    findingPlugins: 'Finding plugins',
    publishing: 'Publishing plugins',
    developmentTutorial: 'Development tutorial',
    securityPractices: 'Security best practices',
    paymentsLicensing: 'Payments & licensing',
    cloudOverview: 'Overview',
    deployGuide: 'Deploy guide',
    autoScaling: 'Auto-scaling',
    costEstimation: 'Cost estimation',
    comparison: 'Other hosts comparison',
    versionMenu: 'v0.0',
    changelog: 'Changelog',
    roadmap: 'Roadmap',
  } as SidebarText,
  'pt-BR': {
    starting: 'Começando',
    concepts: 'Conceitos',
    tutorial: 'Tutorial',
    realtime: 'Realtime',
    integrations: 'Integrações',
    a11y: 'Acessibilidade',
    release: 'Release',
    community: 'Comunidade',
    migration: 'Migração',
    resources: 'Recursos',
    reference: 'API',
    advanced: 'Avançado',
    examples: 'Exemplos',
    marketplace: 'Marketplace',
    laravelCloud: 'Laravel Cloud',
    navDocs: 'Docs',
    navResources: 'Recursos',
    navApi: 'API',
    navAdvanced: 'Avançado',
    navExamples: 'Exemplos',
    navMarketplace: 'Marketplace',
    navLaravelCloud: 'Laravel Cloud',
    whatIs: 'O que é Arqel?',
    gettingStarted: 'Getting Started',
    installation: 'Instalação',
    panels: 'Panels',
    resourcesPage: 'Resources',
    fields: 'Fields',
    tablesForms: 'Tables & Forms',
    actions: 'Actions',
    authentication: 'Authentication (login/registro)',
    authorization: 'Auth (authorization)',
    firstCrud: 'Primeiro CRUD completo',
    collab: 'Edição colaborativa (Yjs + Reverb)',
    agents: 'AGENTS.md (LLMs)',
    a11yGuide: 'Guia a11y (WCAG 2.1 AA)',
    releaseChecklist: 'Release checklist',
    contribute: 'Contribuir',
    fromFilament: 'Vindo de Filament',
    fromNova: 'Vindo de Nova',
    fromReactAdmin: 'Vindo de react-admin',
    resource: 'Resource',
    fieldsRef: 'Fields',
    table: 'Table',
    form: 'Form',
    actionsRef: 'Actions',
    overview: 'Overview',
    customFields: 'Custom Fields',
    macros: 'Macros',
    phase2: 'Phase 2 features',
    multiTenancy: 'Multi-tenancy',
    dashboards: 'Dashboards & Widgets',
    tablesV2: 'Tables V2 enhancements',
    mcp: 'MCP server',
    commandPalette: 'Command palette',
    tools: 'Ferramentas',
    devtools: 'DevTools extension (install)',
    blogAdmin: 'Blog admin',
    demoApp: 'Demo app (showcase)',
    workflows: 'Workflows',
    generalOverview: 'Visão geral',
    orderStates: 'Order states',
    articleStates: 'Article states',
    subscriptionStates: 'Subscription states',
    versioning: 'Versioning',
    cmsArticles: 'CMS articles',
    ecommerceOrders: 'E-commerce orders',
    legalContracts: 'Legal contracts',
    marketplaceOverview: 'Visão geral',
    findingPlugins: 'Encontrando plugins',
    publishing: 'Publicando plugins',
    developmentTutorial: 'Tutorial de desenvolvimento',
    securityPractices: 'Boas práticas de segurança',
    paymentsLicensing: 'Pagamentos & licenças',
    cloudOverview: 'Visão geral',
    deployGuide: 'Deploy guide',
    autoScaling: 'Auto-scaling',
    costEstimation: 'Estimativa de custos',
    comparison: 'Comparação com outros hosts',
    versionMenu: 'v0.0',
    changelog: 'Changelog',
    roadmap: 'Roadmap',
  } as SidebarText,
  es: {
    starting: 'Empezando',
    concepts: 'Conceptos',
    tutorial: 'Tutorial',
    realtime: 'Realtime',
    integrations: 'Integraciones',
    a11y: 'Accesibilidad',
    release: 'Release',
    community: 'Comunidad',
    migration: 'Migración',
    resources: 'Recursos',
    reference: 'API',
    advanced: 'Avanzado',
    examples: 'Ejemplos',
    marketplace: 'Marketplace',
    laravelCloud: 'Laravel Cloud',
    navDocs: 'Docs',
    navResources: 'Recursos',
    navApi: 'API',
    navAdvanced: 'Avanzado',
    navExamples: 'Ejemplos',
    navMarketplace: 'Marketplace',
    navLaravelCloud: 'Laravel Cloud',
    whatIs: '¿Qué es Arqel?',
    gettingStarted: 'Empezando',
    installation: 'Instalación',
    panels: 'Panels',
    resourcesPage: 'Resources',
    fields: 'Fields',
    tablesForms: 'Tables & Forms',
    actions: 'Actions',
    authentication: 'Autenticación (login/registro)',
    authorization: 'Autorización',
    firstCrud: 'Primer CRUD completo',
    collab: 'Edición colaborativa (Yjs + Reverb)',
    agents: 'AGENTS.md (LLMs)',
    a11yGuide: 'Guía a11y (WCAG 2.1 AA)',
    releaseChecklist: 'Release checklist',
    contribute: 'Contribuir',
    fromFilament: 'Viniendo de Filament',
    fromNova: 'Viniendo de Nova',
    fromReactAdmin: 'Viniendo de react-admin',
    resource: 'Resource',
    fieldsRef: 'Fields',
    table: 'Table',
    form: 'Form',
    actionsRef: 'Actions',
    overview: 'Overview',
    customFields: 'Custom Fields',
    macros: 'Macros',
    phase2: 'Funciones Phase 2',
    multiTenancy: 'Multi-tenancy',
    dashboards: 'Dashboards & Widgets',
    tablesV2: 'Tables V2 enhancements',
    mcp: 'Servidor MCP',
    commandPalette: 'Command palette',
    tools: 'Herramientas',
    devtools: 'DevTools extension (instalación)',
    blogAdmin: 'Blog admin',
    demoApp: 'Demo app (showcase)',
    workflows: 'Workflows',
    generalOverview: 'Vista general',
    orderStates: 'Estados de pedidos',
    articleStates: 'Estados de artículos',
    subscriptionStates: 'Estados de suscripciones',
    versioning: 'Versioning',
    cmsArticles: 'Artículos de CMS',
    ecommerceOrders: 'Pedidos de e-commerce',
    legalContracts: 'Contratos legales',
    marketplaceOverview: 'Vista general',
    findingPlugins: 'Encontrando plugins',
    publishing: 'Publicando plugins',
    developmentTutorial: 'Tutorial de desarrollo',
    securityPractices: 'Buenas prácticas de seguridad',
    paymentsLicensing: 'Pagos & licencias',
    cloudOverview: 'Vista general',
    deployGuide: 'Guía de deploy',
    autoScaling: 'Auto-scaling',
    costEstimation: 'Estimación de costos',
    comparison: 'Comparación con otros hosts',
    versionMenu: 'v0.0',
    changelog: 'Changelog',
    roadmap: 'Roadmap',
  } as SidebarText,
};

function buildNav(prefix: string, t: SidebarText): DefaultTheme.NavItem[] {
  return [
    { text: t.navDocs, link: `${prefix}/guide/getting-started`, activeMatch: `${prefix}/guide/` },
    {
      text: t.navResources,
      link: `${prefix}/resources/resource`,
      activeMatch: `${prefix}/resources/`,
    },
    {
      text: t.navApi,
      link: `${prefix}/reference/php-overview`,
      activeMatch: `${prefix}/reference/`,
    },
    {
      text: t.navAdvanced,
      link: `${prefix}/advanced/custom-fields`,
      activeMatch: `${prefix}/advanced/`,
    },
    {
      text: t.navExamples,
      link: `${prefix}/examples/blog-admin`,
      activeMatch: `${prefix}/examples/`,
    },
    {
      text: t.navMarketplace,
      link: `${prefix}/marketplace/`,
      activeMatch: `${prefix}/marketplace/`,
    },
    {
      text: t.navLaravelCloud,
      link: `${prefix}/laravel-cloud/`,
      activeMatch: `${prefix}/laravel-cloud/`,
    },
    {
      text: t.versionMenu,
      items: [
        { text: t.changelog, link: 'https://github.com/arqel-dev/arqel/blob/main/CHANGELOG.md' },
        {
          text: t.roadmap,
          link: 'https://github.com/arqel-dev/arqel/blob/main/PLANNING/07-roadmap-fases.md',
        },
      ],
    },
  ];
}

function buildSidebar(prefix: string, t: SidebarText): DefaultTheme.Sidebar {
  const p = prefix; // '' for root (en), '/pt-BR' for pt, '/es' for es
  return {
    [`${p}/guide/`]: [
      {
        text: t.starting,
        items: [
          { text: t.whatIs, link: `${p}/guide/what-is-arqel` },
          { text: t.gettingStarted, link: `${p}/guide/getting-started` },
          { text: t.installation, link: `${p}/guide/installation` },
        ],
      },
      {
        text: t.concepts,
        items: [
          { text: t.panels, link: `${p}/guide/panels` },
          { text: t.resourcesPage, link: `${p}/guide/resources` },
          { text: t.fields, link: `${p}/guide/fields` },
          { text: t.tablesForms, link: `${p}/guide/tables-forms` },
          { text: t.actions, link: `${p}/guide/actions` },
          { text: t.authentication, link: `${p}/guide/authentication` },
          { text: t.authorization, link: `${p}/guide/auth` },
        ],
      },
      { text: t.tutorial, items: [{ text: t.firstCrud, link: `${p}/guide/tutorial-first-crud` }] },
      { text: t.realtime, items: [{ text: t.collab, link: `${p}/guide/realtime-collab` }] },
      { text: t.integrations, items: [{ text: t.agents, link: `${p}/guide/agents` }] },
      { text: t.a11y, items: [{ text: t.a11yGuide, link: `${p}/guide/a11y` }] },
      {
        text: t.release,
        items: [{ text: t.releaseChecklist, link: `${p}/guide/release-checklist` }],
      },
      { text: t.community, items: [{ text: t.contribute, link: `${p}/guide/contributing` }] },
      {
        text: t.migration,
        items: [
          { text: t.fromFilament, link: `${p}/guide/migration/from-filament` },
          { text: t.fromNova, link: `${p}/guide/migration/from-nova` },
          { text: t.fromReactAdmin, link: `${p}/guide/migration/from-react-admin` },
        ],
      },
    ],
    [`${p}/resources/`]: [
      {
        text: t.resources,
        items: [
          { text: t.resource, link: `${p}/resources/resource` },
          { text: t.fieldsRef, link: `${p}/resources/fields` },
          { text: t.table, link: `${p}/resources/table` },
          { text: t.form, link: `${p}/resources/form` },
          { text: t.actionsRef, link: `${p}/resources/actions` },
        ],
      },
    ],
    [`${p}/reference/`]: [
      {
        text: 'PHP',
        items: [
          { text: t.overview, link: `${p}/reference/php-overview` },
          { text: 'arqel-dev/core', link: `${p}/reference/php/core` },
          { text: 'arqel-dev/fields', link: `${p}/reference/php/fields` },
          { text: 'arqel-dev/table', link: `${p}/reference/php/table` },
          { text: 'arqel-dev/form', link: `${p}/reference/php/form` },
          { text: 'arqel-dev/actions', link: `${p}/reference/php/actions` },
          { text: 'arqel-dev/auth', link: `${p}/reference/php/auth` },
          { text: 'arqel-dev/nav', link: `${p}/reference/php/nav` },
        ],
      },
      {
        text: 'TypeScript',
        items: [
          { text: t.overview, link: `${p}/reference/typescript-overview` },
          { text: '@arqel-dev/types', link: `${p}/reference/typescript/types` },
          { text: '@arqel-dev/react', link: `${p}/reference/typescript/react` },
          { text: '@arqel-dev/hooks', link: `${p}/reference/typescript/hooks` },
          { text: '@arqel-dev/ui', link: `${p}/reference/typescript/ui` },
          { text: '@arqel-dev/fields', link: `${p}/reference/typescript/fields` },
        ],
      },
    ],
    [`${p}/advanced/`]: [
      {
        text: t.advanced,
        items: [
          { text: t.customFields, link: `${p}/advanced/custom-fields` },
          { text: t.macros, link: `${p}/advanced/macros` },
        ],
      },
      {
        text: t.phase2,
        items: [
          { text: t.multiTenancy, link: `${p}/advanced/multi-tenancy` },
          { text: t.dashboards, link: `${p}/advanced/dashboards` },
          { text: t.tablesV2, link: `${p}/advanced/tables-v2` },
          { text: t.mcp, link: `${p}/advanced/mcp` },
          { text: t.commandPalette, link: `${p}/advanced/command-palette` },
        ],
      },
      { text: t.tools, items: [{ text: t.devtools, link: `${p}/devtools-extension/install` }] },
    ],
    [`${p}/examples/`]: [
      {
        text: t.examples,
        items: [
          { text: t.blogAdmin, link: `${p}/examples/blog-admin` },
          { text: t.demoApp, link: `${p}/examples/demo-app` },
        ],
      },
      {
        text: t.workflows,
        items: [
          { text: t.generalOverview, link: `${p}/examples/workflows/` },
          { text: t.orderStates, link: `${p}/examples/workflows/order-states` },
          { text: t.articleStates, link: `${p}/examples/workflows/article-states` },
          { text: t.subscriptionStates, link: `${p}/examples/workflows/subscription-states` },
        ],
      },
      {
        text: t.versioning,
        items: [
          { text: t.generalOverview, link: `${p}/examples/versioning/` },
          { text: t.cmsArticles, link: `${p}/examples/versioning/cms-articles` },
          { text: t.ecommerceOrders, link: `${p}/examples/versioning/ecommerce-orders` },
          { text: t.legalContracts, link: `${p}/examples/versioning/legal-contracts` },
        ],
      },
    ],
    [`${p}/marketplace/`]: [
      {
        text: t.marketplace,
        items: [
          { text: t.marketplaceOverview, link: `${p}/marketplace/` },
          { text: t.findingPlugins, link: `${p}/marketplace/finding-plugins` },
          { text: t.publishing, link: `${p}/marketplace/publishing` },
          { text: t.developmentTutorial, link: `${p}/marketplace/development-tutorial` },
          { text: t.securityPractices, link: `${p}/marketplace/security-best-practices` },
          { text: t.paymentsLicensing, link: `${p}/marketplace/payments-and-licensing` },
        ],
      },
    ],
    [`${p}/laravel-cloud/`]: [
      {
        text: t.laravelCloud,
        items: [
          { text: t.cloudOverview, link: `${p}/laravel-cloud/` },
          { text: t.deployGuide, link: `${p}/laravel-cloud/deploy-guide` },
          { text: t.autoScaling, link: `${p}/laravel-cloud/auto-scaling` },
          { text: t.costEstimation, link: `${p}/laravel-cloud/cost-estimation` },
          { text: t.comparison, link: `${p}/laravel-cloud/comparison-other-hosts` },
        ],
      },
    ],
  };
}

const sharedHead: NonNullable<ReturnType<typeof defineConfig>['head']> = [
  ['link', { rel: 'icon', href: '/favicon.svg', type: 'image/svg+xml' }],
  ['meta', { name: 'theme-color', content: '#6366f1' }],
  ['meta', { property: 'og:title', content: 'Arqel — Admin panels for Laravel' }],
  ['meta', { property: 'og:type', content: 'website' }],
  ['meta', { property: 'og:image', content: ogImage }],
  ['meta', { property: 'og:url', content: 'https://arqel.dev' }],
  ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
];

export default defineConfig({
  title: 'Arqel',
  titleTemplate: ':title — Arqel',
  description: 'Admin panels for Laravel, forged in PHP, rendered in React.',
  cleanUrls: true,
  lastUpdated: true,
  srcExclude: ['**/SKILL.md', '**/README.md'],
  // Multi-language migration in progress: existing markdown still uses
  // absolute /guide/... paths instead of /<locale>/guide/.... We'll
  // tighten this back to a strict allowlist once translations land.
  ignoreDeadLinks: true,

  head: sharedHead,

  themeConfig: {
    logo: '/logo.svg',
    siteTitle: 'Arqel',
    socialLinks: [{ icon: 'github', link: 'https://github.com/arqel-dev/arqel' }],
    search: {
      provider: 'local',
    },
  },

  locales: {
    root: {
      label: 'English',
      lang: 'en',
      themeConfig: {
        nav: buildNav('', T.en),
        sidebar: buildSidebar('', T.en),
        outline: { label: 'On this page' },
        docFooter: { prev: 'Previous', next: 'Next' },
        darkModeSwitchLabel: 'Theme',
        sidebarMenuLabel: 'Menu',
        returnToTopLabel: 'Back to top',
        langMenuLabel: 'Language',
        notFound: {
          title: 'Page not found',
          quote: 'This page was moved or never existed.',
          linkText: 'Go home',
        },
        editLink: {
          pattern: 'https://github.com/arqel-dev/arqel/edit/main/apps/docs/:path',
          text: 'Suggest an edit',
        },
        footer: {
          message: 'MIT License — built with Inertia + React + Laravel.',
          copyright: 'Copyright © 2026 Arqel contributors',
        },
      },
    },
    'pt-BR': {
      label: 'Português (BR)',
      lang: 'pt-BR',
      link: '/pt-BR/',
      themeConfig: {
        nav: buildNav('/pt-BR', T['pt-BR']),
        sidebar: buildSidebar('/pt-BR', T['pt-BR']),
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
        editLink: {
          pattern: 'https://github.com/arqel-dev/arqel/edit/main/apps/docs/:path',
          text: 'Sugerir edição',
        },
        footer: {
          message: 'Licença MIT — construído com Inertia + React + Laravel.',
          copyright: 'Copyright © 2026 contribuidores do Arqel',
        },
      },
    },
    es: {
      label: 'Español',
      lang: 'es',
      link: '/es/',
      themeConfig: {
        nav: buildNav('/es', T.es),
        sidebar: buildSidebar('/es', T.es),
        outline: { label: 'En esta página' },
        docFooter: { prev: 'Anterior', next: 'Siguiente' },
        darkModeSwitchLabel: 'Tema',
        sidebarMenuLabel: 'Menú',
        returnToTopLabel: 'Volver arriba',
        langMenuLabel: 'Idioma',
        notFound: {
          title: 'Página no encontrada',
          quote: 'Esta página fue movida o nunca existió.',
          linkText: 'Volver al inicio',
        },
        editLink: {
          pattern: 'https://github.com/arqel-dev/arqel/edit/main/apps/docs/:path',
          text: 'Sugerir edición',
        },
        footer: {
          message: 'Licencia MIT — construido con Inertia + React + Laravel.',
          copyright: 'Copyright © 2026 contribuidores de Arqel',
        },
      },
    },
  },
});
