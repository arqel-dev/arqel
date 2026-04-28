import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import {
  PanelContext,
  ResourceContext,
  TenantContext,
  useRequiredPanel,
  useRequiredResource,
  useTenant,
} from '../src/context/index.js';

function PanelLabel() {
  const panel = useRequiredPanel();
  return <span data-testid="panel">{panel.id}</span>;
}

function ResourceLabel() {
  const resource = useRequiredResource();
  return <span data-testid="resource">{resource.slug}</span>;
}

function TenantLabel() {
  const tenant = useTenant();
  return <span data-testid="tenant">{tenant === null ? 'guest' : 'tenanted'}</span>;
}

describe('context providers', () => {
  it('useRequiredPanel reads from PanelContext', () => {
    render(
      <PanelContext.Provider
        value={{
          id: 'admin',
          path: '/admin',
          brand: { name: 'Acme', logo: null },
        }}
      >
        <PanelLabel />
      </PanelContext.Provider>,
    );

    expect(screen.getByTestId('panel')).toHaveTextContent('admin');
  });

  it('useRequiredPanel throws when no provider', () => {
    expect(() => render(<PanelLabel />)).toThrow(/no PanelContext/i);
  });

  it('useRequiredResource reads from ResourceContext', () => {
    render(
      <ResourceContext.Provider
        value={{
          class: 'App\\Arqel\\Resources\\UserResource',
          slug: 'users',
          label: 'User',
          pluralLabel: 'Users',
          navigationIcon: null,
          navigationGroup: null,
        }}
      >
        <ResourceLabel />
      </ResourceContext.Provider>,
    );

    expect(screen.getByTestId('resource')).toHaveTextContent('users');
  });

  it('useTenant defaults to null', () => {
    render(<TenantLabel />);

    expect(screen.getByTestId('tenant')).toHaveTextContent('guest');
  });

  it('useTenant returns the provided value', () => {
    render(
      <TenantContext.Provider value={{ id: 1 }}>
        <TenantLabel />
      </TenantContext.Provider>,
    );

    expect(screen.getByTestId('tenant')).toHaveTextContent('tenanted');
  });
});
