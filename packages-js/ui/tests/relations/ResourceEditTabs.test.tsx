import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({ router: { reload: vi.fn(), post: vi.fn() } }));
vi.mock('../../src/relations/RelationManagerPanel.js', () => ({
  RelationManagerPanel: ({ relation }: { relation: { slug: string } }) => (
    <div>panel:{relation.slug}</div>
  ),
}));

import { ResourceEditTabs } from '../../src/relations/ResourceEditTabs.js';

describe('ResourceEditTabs', () => {
  it('renders only the form (no tabs) when there are no relations', () => {
    render(
      <ResourceEditTabs relations={[]} parentSlug="rel-posts" parentId={1}>
        <div>the-form</div>
      </ResourceEditTabs>,
    );
    expect(screen.getByText('the-form')).toBeInTheDocument();
    expect(screen.queryByRole('tab')).toBeNull();
  });

  it('renders a Data tab plus one tab per relation', () => {
    const relations = [
      {
        slug: 'comments',
        label: 'Comments',
        type: 'hasMany' as const,
        table: {},
        fields: [],
        abilities: { create: true, update: true, delete: true, attach: false, detach: false },
      },
    ];
    render(
      <ResourceEditTabs relations={relations} parentSlug="rel-posts" parentId={1}>
        <div>the-form</div>
      </ResourceEditTabs>,
    );
    expect(screen.getByRole('tab', { name: /dados|data/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /comments/i })).toBeInTheDocument();
  });
});
