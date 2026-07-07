import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({ router: { reload: vi.fn(), post: vi.fn() } }));
vi.mock('../../src/relations/RelationManagerPanel.js', () => ({
  RelationManagerPanel: ({
    relation,
    refreshKey,
    onCreate,
  }: {
    relation: { slug: string };
    refreshKey?: number;
    onCreate(): void;
  }) => (
    <div>
      panel:{relation.slug} refreshKey:{refreshKey ?? 0}
      <button type="button" onClick={onCreate}>
        open-create-{relation.slug}
      </button>
    </div>
  ),
}));
vi.mock('../../src/relations/RelationFormModal.js', () => ({
  RelationFormModal: ({ onSuccess }: { onSuccess?: () => void }) => (
    <button type="button" onClick={onSuccess}>
      mutate-comments
    </button>
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

  it('bumps the relation panel refreshKey after a create/edit mutation succeeds (Task 13a)', async () => {
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

    // The relation's `TabsContent` starts inactive/hidden — switch to it so
    // the panel's create button is visible to Testing Library / userEvent.
    await userEvent.click(screen.getByRole('tab', { name: /comments/i }));

    expect(screen.getByText('panel:comments refreshKey:0')).toBeInTheDocument();

    // Open the create modal (mounts the mocked `RelationFormModal`), then
    // simulate its mutation succeeding.
    await userEvent.click(screen.getByText('open-create-comments'));
    await userEvent.click(screen.getByText('mutate-comments'));

    // `ResourceEditTabs` bumped this relation's refreshKey, so the panel
    // (which the real `RelationManagerPanel` uses to re-run its fetch
    // effect) receives the incremented value.
    expect(screen.getByText('panel:comments refreshKey:1')).toBeInTheDocument();
  });
});
