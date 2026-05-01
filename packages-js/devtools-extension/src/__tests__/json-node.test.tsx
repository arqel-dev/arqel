import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { JsonNode } from '../panel/JsonNode';

describe('<JsonNode />', () => {
  it('renders primitive values inline', () => {
    render(<JsonNode value={42} nodeKey="answer" path="$" />);
    expect(screen.getByTestId('json-node-$')).toHaveTextContent('answer: 42');
  });

  it('renders object branch with key count and toggles expansion', () => {
    render(
      <JsonNode value={{ name: 'arqel', version: 'rc' }} nodeKey="pkg" path="$" defaultExpanded />,
    );
    const branch = screen.getByTestId('json-node-$');
    expect(branch).toHaveTextContent('Object{2}');
    // Children visible when expanded.
    expect(screen.getByTestId('json-node-$.name')).toBeInTheDocument();

    fireEvent.click(screen.getByTestId('json-toggle-$'));
    expect(screen.queryByTestId('json-node-$.name')).not.toBeInTheDocument();
  });

  it('renders array entries with numeric keys and Array(n) label', () => {
    render(<JsonNode value={[1, 2, 3]} nodeKey="nums" path="$" defaultExpanded />);
    expect(screen.getByTestId('json-node-$')).toHaveTextContent('Array(3)');
    expect(screen.getByTestId('json-node-$.0')).toHaveTextContent('0: 1');
    expect(screen.getByTestId('json-node-$.2')).toHaveTextContent('2: 3');
  });

  it('marks nodes that match search and highlights the term', () => {
    render(
      <JsonNode
        value={{ alpha: 'one', beta: 'two' }}
        nodeKey="root"
        path="$"
        search="alp"
        defaultExpanded
      />,
    );
    expect(screen.getByTestId('json-node-$')).toHaveAttribute('data-match', 'yes');
    expect(screen.getByTestId('json-node-$.alpha')).toHaveAttribute('data-match', 'yes');
    expect(screen.getByTestId('json-node-$.beta')).toHaveAttribute('data-match', 'no');
    // Highlight wraps the matching slice in <mark>.
    expect(screen.getAllByTestId('json-highlight').length).toBeGreaterThan(0);
  });

  it('matches values not just keys', () => {
    render(
      <JsonNode
        value={{ name: 'arqel-devtools' }}
        nodeKey="pkg"
        path="$"
        search="devtools"
        defaultExpanded
      />,
    );
    expect(screen.getByTestId('json-node-$.name')).toHaveAttribute('data-match', 'yes');
  });
});
