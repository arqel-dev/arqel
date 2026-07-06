import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({ router: { post: vi.fn(), delete: vi.fn() } }));
vi.mock('../../src/table/DataTable.js', () => ({
  DataTable: () => <div data-testid="data-table" />,
}));

import { RelationManagerPanel } from '../../src/relations/RelationManagerPanel.js';

const base = {
  slug: 'comments',
  label: 'Comments',
  type: 'hasMany' as const,
  table: {},
  fields: [],
  abilities: { create: true, update: true, delete: true, attach: false, detach: false },
};

describe('RelationManagerPanel', () => {
  it('renders the data table and a New button when create is allowed', () => {
    render(
      <RelationManagerPanel
        relation={base}
        parentSlug="rel-posts"
        parentId={1}
        records={[]}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
      />,
    );
    expect(screen.getByTestId('data-table')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /new/i })).toBeInTheDocument();
  });

  it('hides the New button when create is not allowed', () => {
    render(
      <RelationManagerPanel
        relation={{ ...base, abilities: { ...base.abilities, create: false } }}
        parentSlug="rel-posts"
        parentId={1}
        records={[]}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
      />,
    );
    expect(screen.queryByRole('button', { name: /new/i })).toBeNull();
  });

  it('shows an Attach button only for belongsToMany with attach allowed', () => {
    render(
      <RelationManagerPanel
        relation={{
          ...base,
          type: 'belongsToMany',
          abilities: { ...base.abilities, attach: true },
        }}
        parentSlug="rel-posts"
        parentId={1}
        records={[]}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
      />,
    );
    expect(screen.getByRole('button', { name: /attach/i })).toBeInTheDocument();
  });
});
