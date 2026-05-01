import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PolicyDebugger, type PolicyLogEntry } from '../PolicyDebugger';

function makeEntries(): PolicyLogEntry[] {
  return [
    {
      ability: 'view-users',
      arguments: [{ id: 1 }],
      result: true,
      backtrace: [
        { file: '/app/A.php', line: 10, class: 'App\\A', function: 'do' },
        { file: '/app/B.php', line: 20, class: null, function: 'closure' },
      ],
    },
    {
      ability: 'update-posts',
      arguments: [{ id: 7 }],
      result: false,
      backtrace: [],
    },
    {
      ability: 'delete-comments',
      arguments: [],
      result: true,
      backtrace: [],
    },
  ];
}

describe('<PolicyDebugger />', () => {
  it('renders one row per policy log entry', () => {
    render(<PolicyDebugger entries={makeEntries()} />);
    expect(screen.getAllByTestId('policy-row')).toHaveLength(3);
    expect(screen.getByText('view-users')).toBeInTheDocument();
    expect(screen.getByText('update-posts')).toBeInTheDocument();
    expect(screen.getByText('delete-comments')).toBeInTheDocument();
  });

  it('aggregates counter as "X allowed / Y denied"', () => {
    render(<PolicyDebugger entries={makeEntries()} />);
    expect(screen.getByTestId('policy-counter')).toHaveTextContent('2 allowed / 1 denied');
  });

  it('filters by allow/deny via the result selector', () => {
    render(<PolicyDebugger entries={makeEntries()} />);
    const selector = screen.getByTestId('policy-result-filter');

    fireEvent.change(selector, { target: { value: 'deny' } });
    expect(screen.getAllByTestId('policy-row')).toHaveLength(1);
    expect(screen.getByText('update-posts')).toBeInTheDocument();

    fireEvent.change(selector, { target: { value: 'allow' } });
    expect(screen.getAllByTestId('policy-row')).toHaveLength(2);
  });

  it('filters by ability search term (case-insensitive substring)', () => {
    render(<PolicyDebugger entries={makeEntries()} />);
    fireEvent.change(screen.getByTestId('policy-search'), { target: { value: 'POSTS' } });

    const rows = screen.getAllByTestId('policy-row');
    expect(rows).toHaveLength(1);
    expect(screen.getByText('update-posts')).toBeInTheDocument();
  });

  it('toggles the stack trace open/closed per row', () => {
    render(<PolicyDebugger entries={makeEntries()} />);
    expect(screen.queryByTestId('policy-stack')).not.toBeInTheDocument();

    const toggles = screen.getAllByTestId('policy-stack-toggle');
    fireEvent.click(toggles[0] as HTMLElement);

    expect(screen.getByTestId('policy-stack')).toBeInTheDocument();
    expect(screen.getByText(/App\\A::do/)).toBeInTheDocument();

    fireEvent.click(toggles[0] as HTMLElement);
    expect(screen.queryByTestId('policy-stack')).not.toBeInTheDocument();
  });

  it('shows empty state when there are no entries', () => {
    render(<PolicyDebugger entries={[]} />);
    expect(screen.getByTestId('policy-empty')).toHaveTextContent(
      'No policy checks recorded for this request.',
    );
    expect(screen.queryByTestId('policy-table')).not.toBeInTheDocument();
  });
});
