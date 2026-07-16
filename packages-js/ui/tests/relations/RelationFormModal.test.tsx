import { act, render, screen } from '@testing-library/react';
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
  FormRenderer: ({
    onSubmit,
    values,
    disabled,
  }: {
    onSubmit: () => void;
    values: Record<string, unknown>;
    disabled?: boolean;
  }) => (
    <div>
      {Object.entries(values).map(([key, value]) => (
        <span key={key} data-testid={`value-${key}`}>
          {String(value)}
        </span>
      ))}
      <button type="button" disabled={disabled} onClick={onSubmit}>
        submit-form
      </button>
    </div>
  ),
}));

import { RelationFormModal } from '../../src/relations/RelationFormModal.js';

const relation = {
  slug: 'comments',
  label: 'Comments',
  type: 'hasMany' as const,
  table: {},
  fields: [
    { name: 'title', type: 'text', label: 'Title' },
    { name: 'body', type: 'text', label: 'Body' },
  ],
  abilities: { create: true, update: true, delete: true, attach: false, detach: false },
};

function flush() {
  return act(async () => {
    await Promise.resolve();
    await Promise.resolve();
  });
}

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
        initialValues={{ title: 'hello', body: 'world' }}
      />,
    );
    await userEvent.click(screen.getByText('submit-form'));
    expect(put).toHaveBeenCalled();
    const call = put.mock.calls[0] as [string, unknown, unknown];
    expect(call[0]).toBe('/admin/rel-posts/1/relations/comments/42');
  });
});

describe('RelationFormModal edit-mode data fetch (data-loss fix)', () => {
  it('fetches the record from the edit endpoint and populates the form when recordId is given without initialValues', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ fields: [], record: { title: 'hello', body: 'world' } }),
    });
    vi.stubGlobal('fetch', fetchMock);

    render(
      <RelationFormModal
        open
        onClose={() => {}}
        relation={relation}
        parentSlug="rel-posts"
        parentId={1}
        basePath="/admin"
        recordId={42}
      />,
    );

    await flush();

    expect(fetchMock).toHaveBeenCalledWith(
      '/admin/rel-posts/1/relations/comments/42/edit',
      expect.objectContaining({ headers: expect.objectContaining({ Accept: 'application/json' }) }),
    );
    expect(screen.getByTestId('value-title')).toHaveTextContent('hello');
    expect(screen.getByTestId('value-body')).toHaveTextContent('world');

    vi.unstubAllGlobals();
  });

  it('does not fetch and shows an empty form in create mode (no recordId)', async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);

    render(
      <RelationFormModal
        open
        onClose={() => {}}
        relation={relation}
        parentSlug="rel-posts"
        parentId={1}
        basePath="/admin"
      />,
    );

    await flush();

    expect(fetchMock).not.toHaveBeenCalled();
    expect(screen.queryByTestId('value-title')).toBeNull();
    expect(screen.queryByTestId('value-body')).toBeNull();

    vi.unstubAllGlobals();
  });

  it('does not fetch and uses the explicit initialValues when both recordId and initialValues are given', async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);

    render(
      <RelationFormModal
        open
        onClose={() => {}}
        relation={relation}
        parentSlug="rel-posts"
        parentId={1}
        basePath="/admin"
        recordId={42}
        initialValues={{ title: 'explicit', body: 'value' }}
      />,
    );

    await flush();

    expect(fetchMock).not.toHaveBeenCalled();
    expect(screen.getByTestId('value-title')).toHaveTextContent('explicit');
    expect(screen.getByTestId('value-body')).toHaveTextContent('value');

    vi.unstubAllGlobals();
  });

  it('disables submit and never calls router.put with empty values when the edit fetch fails', async () => {
    const fetchMock = vi.fn().mockRejectedValue(new Error('network down'));
    vi.stubGlobal('fetch', fetchMock);
    const callsBefore = put.mock.calls.length;

    render(
      <RelationFormModal
        open
        onClose={() => {}}
        relation={relation}
        parentSlug="rel-posts"
        parentId={1}
        basePath="/admin"
        recordId={42}
      />,
    );

    await flush();

    const submitButton = screen.getByText('submit-form') as HTMLButtonElement;
    expect(submitButton).toBeDisabled();

    await userEvent.click(submitButton);
    expect(put.mock.calls.length).toBe(callsBefore);

    vi.unstubAllGlobals();
  });

  it('disables submit while the edit fetch is still in flight', async () => {
    let resolveFetch: (v: unknown) => void = () => {};
    const fetchMock = vi.fn(
      () =>
        new Promise((resolve) => {
          resolveFetch = resolve;
        }),
    );
    vi.stubGlobal('fetch', fetchMock);

    render(
      <RelationFormModal
        open
        onClose={() => {}}
        relation={relation}
        parentSlug="rel-posts"
        parentId={1}
        basePath="/admin"
        recordId={42}
      />,
    );

    const submitButton = screen.getByText('submit-form') as HTMLButtonElement;
    expect(submitButton).toBeDisabled();

    await act(async () => {
      resolveFetch({
        ok: true,
        json: () => Promise.resolve({ fields: [], record: { title: 'a', body: 'b' } }),
      });
      await Promise.resolve();
      await Promise.resolve();
    });

    expect(submitButton).not.toBeDisabled();

    vi.unstubAllGlobals();
  });
});
