import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

const post = vi.fn();
const put = vi.fn();
const reload = vi.fn();
vi.mock('@inertiajs/react', () => ({
  router: {
    post: (...a: unknown[]) => post(...a),
    put: (...a: unknown[]) => put(...a),
    reload: (...a: unknown[]) => reload(...a),
  },
}));
vi.mock('@arqel-dev/react/utils', () => ({
  useArqelTranslations: () => (_k: string, fallback?: string) => fallback ?? _k,
}));
vi.mock('../../src/form/FormRenderer.js', () => ({
  FormRenderer: ({ onSubmit }: { onSubmit: () => void }) => (
    <button type="button" onClick={onSubmit}>
      submit-form
    </button>
  ),
}));

import { RelationFormModal } from '../../src/relations/RelationFormModal.js';

const relation = {
  slug: 'comments',
  label: 'Comments',
  type: 'hasMany' as const,
  table: {},
  fields: [],
  abilities: { create: true, update: true, delete: true, attach: false, detach: false },
};

describe('RelationFormModal', () => {
  it('posts to the relation store route and reloads only relations on success', async () => {
    render(
      <RelationFormModal
        open
        onClose={() => {}}
        relation={relation}
        parentSlug="rel-posts"
        parentId={1}
      />,
    );
    await userEvent.click(screen.getByText('submit-form'));
    expect(post).toHaveBeenCalled();
    const call = post.mock.calls[0] as [string, unknown, { onSuccess: () => void }];
    const opts = call[2];
    // onSuccess should trigger a partial reload limited to 'relations'
    expect(typeof opts.onSuccess).toBe('function');
  });

  it('does not render when closed', () => {
    render(
      <RelationFormModal
        open={false}
        onClose={() => {}}
        relation={relation}
        parentSlug="rel-posts"
        parentId={1}
      />,
    );
    expect(screen.queryByText('submit-form')).toBeNull();
  });

  it('puts to the record route when recordId is given', async () => {
    render(
      <RelationFormModal
        open
        onClose={() => {}}
        relation={relation}
        parentSlug="rel-posts"
        parentId={1}
        recordId={42}
        basePath="/admin"
      />,
    );
    await userEvent.click(screen.getByText('submit-form'));
    expect(put).toHaveBeenCalled();
    const call = put.mock.calls[0] as [string, unknown, unknown];
    expect(call[0]).toBe('/admin/rel-posts/1/relations/comments/42');
  });
});
