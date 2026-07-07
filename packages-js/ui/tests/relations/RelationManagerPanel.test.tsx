import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

// `ConfirmDialog` (used for the Delete/Detach row actions, Task 13c) reads
// `usePage()` via `useArqelTranslations`; mock it so this panel keeps
// rendering outside a real Inertia app, matching `ActionButton.test.tsx`.
vi.mock('@inertiajs/react', () => ({
  router: { post: vi.fn(), delete: vi.fn() },
  usePage: () => ({ props: {} }),
}));

import { router } from '@inertiajs/react';

interface MockDataTableProps {
  records: unknown[];
  rowActions?: (record: { id: string | number }) => React.ReactNode;
}

vi.mock('../../src/table/DataTable.js', () => ({
  DataTable: ({ records, rowActions }: MockDataTableProps) => (
    <div data-testid="data-table" data-record-count={records.length}>
      {records.map((r) => (
        <div key={(r as { id: string | number }).id}>
          record:{(r as { id: string | number }).id}
          {rowActions?.(r as { id: string | number })}
        </div>
      ))}
    </div>
  ),
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

describe('RelationManagerPanel records fetch (Task 13a)', () => {
  it('fetches records from the RelationController::index() endpoint and feeds them to the DataTable', async () => {
    const fetcher = vi.fn().mockResolvedValue({
      records: [{ id: 1, body: 'hello' }],
      table: {},
      abilities: base.abilities,
    });

    render(
      <RelationManagerPanel
        relation={base}
        parentSlug="rel-posts"
        parentId={1}
        basePath="/admin"
        records={[]}
        fetcher={fetcher}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
      />,
    );

    await act(async () => {
      await Promise.resolve();
    });

    expect(fetcher).toHaveBeenCalledWith('/admin/rel-posts/1/relations/comments');
    expect(screen.getByTestId('data-table')).toHaveAttribute('data-record-count', '1');
    expect(screen.getByText('record:1')).toBeInTheDocument();
  });

  it('keeps the initial records seed on screen when the fetch fails', async () => {
    const fetcher = vi.fn().mockRejectedValue(new Error('network down'));

    render(
      <RelationManagerPanel
        relation={base}
        parentSlug="rel-posts"
        parentId={1}
        records={[{ id: 9 }]}
        fetcher={fetcher}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
      />,
    );

    await act(async () => {
      await Promise.resolve();
    });

    expect(screen.getByTestId('data-table')).toHaveAttribute('data-record-count', '1');
    expect(screen.getByText('record:9')).toBeInTheDocument();
  });

  it('refetches when refreshKey changes (post-mutation refresh)', async () => {
    const fetcher = vi
      .fn()
      .mockResolvedValueOnce({ records: [{ id: 1 }] })
      .mockResolvedValueOnce({ records: [{ id: 1 }, { id: 2 }] });

    const { rerender } = render(
      <RelationManagerPanel
        relation={base}
        parentSlug="rel-posts"
        parentId={1}
        records={[]}
        fetcher={fetcher}
        refreshKey={0}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
      />,
    );

    await act(async () => {
      await Promise.resolve();
    });
    expect(screen.getByTestId('data-table')).toHaveAttribute('data-record-count', '1');

    rerender(
      <RelationManagerPanel
        relation={base}
        parentSlug="rel-posts"
        parentId={1}
        records={[]}
        fetcher={fetcher}
        refreshKey={1}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
      />,
    );

    await act(async () => {
      await Promise.resolve();
    });

    expect(fetcher).toHaveBeenCalledTimes(2);
    expect(screen.getByTestId('data-table')).toHaveAttribute('data-record-count', '2');
  });
});

describe('RelationManagerPanel row actions — Delete/Detach (Task 13c)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a Delete row action for hasMany when delete is allowed', () => {
    render(
      <RelationManagerPanel
        relation={base}
        parentSlug="rel-posts"
        parentId={1}
        records={[{ id: 1 }]}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
      />,
    );
    expect(screen.getByRole('button', { name: /delete/i })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /detach/i })).toBeNull();
  });

  it('hides Delete for hasMany when delete is not allowed', () => {
    render(
      <RelationManagerPanel
        relation={{ ...base, abilities: { ...base.abilities, delete: false } }}
        parentSlug="rel-posts"
        parentId={1}
        records={[{ id: 1 }]}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
      />,
    );
    expect(screen.queryByRole('button', { name: /delete/i })).toBeNull();
  });

  it('never shows Delete for belongsToMany, even when delete is allowed', () => {
    render(
      <RelationManagerPanel
        relation={{
          ...base,
          type: 'belongsToMany',
          abilities: { ...base.abilities, delete: true, detach: true },
        }}
        parentSlug="rel-posts"
        parentId={1}
        records={[{ id: 1 }]}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
      />,
    );
    expect(screen.queryByRole('button', { name: /^delete$/i })).toBeNull();
  });

  it('shows a Detach row action for belongsToMany when detach is allowed', () => {
    render(
      <RelationManagerPanel
        relation={{
          ...base,
          type: 'belongsToMany',
          abilities: { ...base.abilities, detach: true },
        }}
        parentSlug="rel-posts"
        parentId={1}
        records={[{ id: 1 }]}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
      />,
    );
    expect(screen.getByRole('button', { name: /detach/i })).toBeInTheDocument();
  });

  it('hides Detach for belongsToMany when detach is not allowed', () => {
    render(
      <RelationManagerPanel
        relation={{
          ...base,
          type: 'belongsToMany',
          abilities: { ...base.abilities, detach: false },
        }}
        parentSlug="rel-posts"
        parentId={1}
        records={[{ id: 1 }]}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
      />,
    );
    expect(screen.queryByRole('button', { name: /detach/i })).toBeNull();
  });

  it('never shows Detach for hasMany, even when detach is allowed', () => {
    render(
      <RelationManagerPanel
        relation={{ ...base, abilities: { ...base.abilities, detach: true } }}
        parentSlug="rel-posts"
        parentId={1}
        records={[{ id: 1 }]}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
      />,
    );
    expect(screen.queryByRole('button', { name: /detach/i })).toBeNull();
  });

  it('confirms then calls router.delete on the destroy URL, and calls onMutated on success', async () => {
    const onMutated = vi.fn();
    render(
      <RelationManagerPanel
        relation={base}
        parentSlug="rel-posts"
        parentId={1}
        basePath="/admin"
        records={[{ id: 7 }]}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
        onMutated={onMutated}
      />,
    );

    await userEvent.click(screen.getByRole('button', { name: /delete/i }));
    // Confirm dialog should appear before any request fires.
    expect(router.delete).not.toHaveBeenCalled();
    await userEvent.click(screen.getByRole('button', { name: /^delete$/i, hidden: false }));

    expect(router.delete).toHaveBeenCalledWith(
      '/admin/rel-posts/1/relations/comments/7',
      expect.objectContaining({ preserveScroll: true }),
    );

    const call = (router.delete as ReturnType<typeof vi.fn>).mock.calls.at(-1) as [
      string,
      { onSuccess: () => void },
    ];
    call[1].onSuccess();
    expect(onMutated).toHaveBeenCalled();
  });

  it('confirms then calls router.delete on the detach URL for belongsToMany', async () => {
    const onMutated = vi.fn();
    render(
      <RelationManagerPanel
        relation={{
          ...base,
          type: 'belongsToMany',
          abilities: { ...base.abilities, detach: true },
        }}
        parentSlug="rel-posts"
        parentId={1}
        basePath="/admin"
        records={[{ id: 7 }]}
        onEdit={() => {}}
        onCreate={() => {}}
        onAttach={() => {}}
        onMutated={onMutated}
      />,
    );

    await userEvent.click(screen.getByRole('button', { name: /detach/i }));
    await userEvent.click(screen.getByRole('button', { name: /^detach$/i, hidden: false }));

    expect(router.delete).toHaveBeenCalledWith(
      '/admin/rel-posts/1/relations/comments/7/detach',
      expect.objectContaining({ preserveScroll: true }),
    );

    const call = (router.delete as ReturnType<typeof vi.fn>).mock.calls.at(-1) as [
      string,
      { onSuccess: () => void },
    ];
    call[1].onSuccess();
    expect(onMutated).toHaveBeenCalled();
  });
});
