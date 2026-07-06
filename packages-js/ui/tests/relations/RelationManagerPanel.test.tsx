import { act, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({ router: { post: vi.fn(), delete: vi.fn() } }));

interface MockDataTableProps {
  records: unknown[];
}

vi.mock('../../src/table/DataTable.js', () => ({
  DataTable: ({ records }: MockDataTableProps) => (
    <div data-testid="data-table" data-record-count={records.length}>
      {records.map((r) => (
        <div key={(r as { id: string | number }).id}>
          record:{(r as { id: string | number }).id}
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
