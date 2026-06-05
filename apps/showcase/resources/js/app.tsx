import {
  ForgotPasswordPage,
  LoginPage,
  RegisterPage,
  ResetPasswordPage,
  VerifyEmailNoticePage,
} from '@arqel-dev/auth';
import { createArqelApp } from '@arqel-dev/react/inertia';
import type { TenantContextProps } from '@arqel-dev/types/tenant';
import { arqelPages } from '@arqel-dev/ui/pages';
import { AppShell, Sidebar, TenantSwitcher, Topbar } from '@arqel-dev/ui/shell';
import '@arqel-dev/fields/register';
import '@arqel-dev/fields-advanced/register';
import { usePage } from '@inertiajs/react';
import type { ComponentType, JSX, ReactNode } from 'react';

type LazyPage = () => Promise<{ default: ComponentType<unknown> }>;

type LayoutFn = (page: ReactNode) => ReactNode;

type LayoutComponent = ComponentType<unknown> & { layout?: LayoutFn };

interface SharedTenantProps {
  tenant?: TenantContextProps;
}

/**
 * Reads the native `tenant` prop (populated by the Arqel core) and
 * renders the switcher. Lives as a component (not inline) so it can call
 * `usePage()`. Renders nothing until a current tenant is shared, so auth
 * pages stay clean.
 */
function TenantSwitcherSlot(): JSX.Element | null {
  const { props } = usePage<SharedTenantProps>();
  const tenant = props.tenant;
  if (!tenant?.current) {
    return null;
  }
  return <TenantSwitcher current={tenant.current} available={tenant.available} />;
}

/**
 * Layout persistente para páginas de Resources do painel admin.
 * Páginas de auth (Login, Register, etc.) renderizam standalone sem
 * AppShell.
 */
const adminLayout: LayoutFn = (page) => (
  <AppShell
    variant="sidebar-left"
    sidebar={<Sidebar brand={<span className="font-semibold">{'Arqel Showcase'}</span>} />}
    topbar={
      <Topbar
        brand={<span className="font-medium">{'Arqel Showcase'}</span>}
        tenantSwitcher={<TenantSwitcherSlot />}
      />
    }
  >
    {page}
  </AppShell>
);

/**
 * Embrulha o loader de uma página Inertia para que o componente
 * resolvido carregue um `layout` estático persistente — Inertia trata
 * `Component.layout` como layout persistente, só re-renderiza o
 * conteúdo interno entre navegações.
 */
function withAdminLayout(loader: LazyPage): LazyPage {
  return async () => {
    const mod = await loader();
    const Component = mod.default as LayoutComponent;
    if (!Component.layout) {
      Component.layout = adminLayout;
    }
    return { default: Component };
  };
}

const wrappedArqelPages: Record<string, LazyPage> = Object.fromEntries(
  Object.entries(arqelPages).map(([key, loader]) => [key, withAdminLayout(loader as LazyPage)]),
);

const authPages: Record<string, LazyPage> = {
  'arqel-dev/auth/Login': async () => ({ default: LoginPage as ComponentType<unknown> }),
  'arqel-dev/auth/Register': async () => ({ default: RegisterPage as ComponentType<unknown> }),
  'arqel-dev/auth/ForgotPassword': async () => ({
    default: ForgotPasswordPage as ComponentType<unknown>,
  }),
  'arqel-dev/auth/ResetPassword': async () => ({
    default: ResetPasswordPage as ComponentType<unknown>,
  }),
  'arqel-dev/auth/VerifyEmailNotice': async () => ({
    default: VerifyEmailNoticePage as ComponentType<unknown>,
  }),
};

const userPages = import.meta.glob<{ default: ComponentType<unknown> }>('./Pages/**/*.tsx');

void createArqelApp({
  appName: import.meta.env.VITE_APP_NAME ?? 'Laravel',
  pages: {
    ...wrappedArqelPages,
    ...authPages,
    ...(userPages as unknown as Record<string, LazyPage>),
  },
});
