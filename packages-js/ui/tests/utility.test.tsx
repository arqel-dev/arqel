import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Breadcrumbs } from '../src/utility/Breadcrumbs.js';
import { EmptyState } from '../src/utility/EmptyState.js';
import { ErrorState } from '../src/utility/ErrorState.js';
import { LoadingSkeleton } from '../src/utility/LoadingSkeleton.js';
import { PageHeader } from '../src/utility/PageHeader.js';

describe('Breadcrumbs', () => {
  it('renders explicit items with last marked aria-current=page', () => {
    render(
      <Breadcrumbs
        items={[{ label: 'Home', url: '/' }, { label: 'Users', url: '/users' }, { label: 'Ada' }]}
      />,
    );
    expect(screen.getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/');
    expect(screen.getByText('Ada')).toHaveAttribute('aria-current', 'page');
  });

  it('returns null when no items', () => {
    const { container } = render(<Breadcrumbs items={[]} />);
    expect(container).toBeEmptyDOMElement();
  });
});

describe('PageHeader', () => {
  it('renders title, description and action slot', () => {
    render(
      <PageHeader
        title="Users"
        description="Manage your team."
        actions={<button type="button">New</button>}
      />,
    );
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Users');
    expect(screen.getByText('Manage your team.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'New' })).toBeInTheDocument();
  });
});

describe('EmptyState', () => {
  it('renders title, description and action', () => {
    render(
      <EmptyState
        title="No users yet"
        description="Invite someone to get started."
        action={<button type="button">Invite</button>}
      />,
    );
    expect(screen.getByText('No users yet')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Invite' })).toBeInTheDocument();
  });
});

describe('ErrorState', () => {
  it('renders status and is announced as alert', () => {
    render(<ErrorState status={404} title="Not found" />);
    const alert = screen.getByRole('alert');
    expect(alert).toHaveTextContent('Not found');
    expect(alert).toHaveTextContent('404');
  });
});

describe('LoadingSkeleton', () => {
  it('renders the requested count of placeholders', () => {
    const { container } = render(<LoadingSkeleton count={3} />);
    expect(container.querySelectorAll('.animate-pulse')).toHaveLength(3);
  });
});
