/**
 * `<TenantSwitcher>` — dropdown that lists tenants the user can
 * access and dispatches `POST /admin/tenants/{id}/switch` via
 * Inertia router. Renders nothing when there is no current tenant
 * or the user only has access to one tenant.
 *
 * Mounts in the `tenantSwitcher` slot of `<Topbar>`. The current
 * tenant is read from `props.tenant.current` shared by the
 * server-side `HandleArqelInertiaRequests`; available tenants
 * come from the same payload.
 */

import { useArqelTranslations } from '@arqel-dev/react/utils';
import type { TenantSummary } from '@arqel-dev/types/tenant';
import { router } from '@inertiajs/react';
import type { JSX } from 'react';
import { Button } from '../action/Button.js';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '../shadcn/ui/dropdown-menu.js';

export interface TenantSwitcherProps {
  current: TenantSummary | null;
  available: ReadonlyArray<TenantSummary>;
  /**
   * Path template for switching; defaults to
   * `/admin/tenants/{id}/switch`.
   */
  switchUrl?: (tenantId: string | number) => string;
}

const DEFAULT_SWITCH_URL = (id: string | number): string => `/admin/tenants/${id}/switch`;

export function TenantSwitcher({
  current,
  available,
  switchUrl,
}: TenantSwitcherProps): JSX.Element | null {
  const t = useArqelTranslations();
  if (!current || available.length <= 1) return null;

  const buildUrl = switchUrl ?? DEFAULT_SWITCH_URL;
  const labelFor = (tenant: TenantSummary): string =>
    tenant.name?.trim()
      ? tenant.name
      : t('arqel.tenant.unnamed', `Tenant ${tenant.id}`, { id: tenant.id });
  const currentLabel = labelFor(current);

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size="sm"
          data-testid="tenant-switcher-trigger"
          aria-label={t('arqel.aria.tenant_switch', `Switch tenant (current: ${currentLabel})`, {
            tenant: currentLabel,
          })}
        >
          {currentLabel}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {available.map((tenant) => {
          const isCurrent = tenant.id === current.id;
          return (
            <DropdownMenuItem
              key={String(tenant.id)}
              data-testid={`tenant-switcher-option-${String(tenant.id)}`}
              disabled={isCurrent}
              onSelect={() => {
                router.post(buildUrl(tenant.id), {}, { preserveScroll: true });
              }}
            >
              {labelFor(tenant)}
              {isCurrent ? ' ✓' : ''}
            </DropdownMenuItem>
          );
        })}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
