import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

const post = vi.fn();
const reload = vi.fn();
vi.mock('@inertiajs/react', () => ({
  router: {
    post: (...a: unknown[]) => post(...a),
    reload: (...a: unknown[]) => reload(...a),
  },
}));
vi.mock('@arqel-dev/react/utils', () => ({
  useArqelTranslations: () => (_k: string, fallback?: string) => fallback ?? _k,
}));

import { AttachModal } from '../../src/relations/AttachModal.js';

const relation = {
  slug: 'tags',
  label: 'Tags',
  type: 'belongsToMany' as const,
  table: {},
  fields: [],
  abilities: { create: true, update: true, delete: true, attach: true, detach: true },
};

describe('AttachModal', () => {
  it('posts the picked related id to the attach route and reloads only relations on success', async () => {
    render(
      <AttachModal
        open
        onClose={() => {}}
        relation={relation}
        parentSlug="rel-posts"
        parentId={1}
        basePath="/admin"
      />,
    );

    await userEvent.type(screen.getByRole('textbox', { name: /tags/i }), '7');
    await userEvent.click(screen.getByRole('button', { name: /attach/i }));

    expect(post).toHaveBeenCalledWith(
      '/admin/rel-posts/1/relations/tags/attach',
      expect.objectContaining({ related: '7' }),
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    );
  });

  it('does not render when closed', () => {
    render(
      <AttachModal
        open={false}
        onClose={() => {}}
        relation={relation}
        parentSlug="rel-posts"
        parentId={1}
      />,
    );
    expect(screen.queryByRole('button', { name: /attach/i })).toBeNull();
  });
});
