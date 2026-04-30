import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import {
  buildMermaidSource,
  type WorkflowDefinitionShape,
  WorkflowVisualizer,
} from './WorkflowVisualizer.js';

function makeDefinition(): WorkflowDefinitionShape {
  return {
    field: 'order_state',
    states: {
      'App\\States\\Pending': { label: 'Pending', color: null, icon: null },
      'App\\States\\Paid': { label: 'Paid', color: '#10b981', icon: null },
      'App\\States\\Shipped': { label: 'Shipped', color: null, icon: null },
    },
    transitions: [
      { from: 'App\\States\\Pending', to: 'App\\States\\Paid', label: 'App\\Transitions\\Pay' },
      { from: 'App\\States\\Paid', to: 'App\\States\\Shipped', label: 'Ship' },
    ],
  };
}

describe('<WorkflowVisualizer>', () => {
  it('renders a <pre> with Mermaid graph LR by default', () => {
    render(<WorkflowVisualizer definition={makeDefinition()} />);
    const pre = screen.getByTestId('workflow-visualizer-source');
    expect(pre.tagName).toBe('PRE');
    expect(pre.className).toContain('language-mermaid');
    expect(pre.textContent).toContain('graph LR');
  });

  it('emits compact slugged edges for FQCN states', () => {
    const source = buildMermaidSource(makeDefinition());
    expect(source).toContain('Pending -->|pay| Paid');
    expect(source).toContain('Paid -->|ship| Shipped');
  });

  it('appends a fill style line when currentState is provided', () => {
    const source = buildMermaidSource(makeDefinition(), 'App\\States\\Paid');
    expect(source).toMatch(/style Paid fill:#fbbf24,stroke:#d97706,stroke-width:2px/);
  });

  it('respects direction prop (TB)', () => {
    render(<WorkflowVisualizer definition={makeDefinition()} direction="TB" />);
    const pre = screen.getByTestId('workflow-visualizer-source');
    expect(pre.textContent).toContain('graph TB');
    expect(pre.textContent).not.toContain('graph LR');
  });

  it('expands from=null into edges from every registered state', () => {
    const def: WorkflowDefinitionShape = {
      field: 'state',
      states: {
        a: { label: 'A' },
        b: { label: 'B' },
        c: { label: 'C' },
      },
      transitions: [{ from: null, to: 'a', label: 'reset' }],
    };
    const source = buildMermaidSource(def);
    expect(source).toContain('a -->|reset| a');
    expect(source).toContain('b -->|reset| a');
    expect(source).toContain('c -->|reset| a');
  });

  it('expands from=array into one edge per origin', () => {
    const def: WorkflowDefinitionShape = {
      field: 'state',
      states: {
        A: { label: 'A' },
        B: { label: 'B' },
        C: { label: 'C' },
      },
      transitions: [{ from: ['A', 'B'], to: 'C', label: 'merge' }],
    };
    const source = buildMermaidSource(def);
    const matches = source.match(/-->\|merge\| C/g) ?? [];
    expect(matches.length).toBe(2);
    expect(source).toContain('A -->|merge| C');
    expect(source).toContain('B -->|merge| C');
  });

  it('invokes the custom renderer prop with the source string', () => {
    const renderer = vi.fn((src: string) => <div data-testid="custom-render">{src}</div>);
    render(<WorkflowVisualizer definition={makeDefinition()} renderer={renderer} />);
    expect(renderer).toHaveBeenCalledTimes(1);
    const arg = renderer.mock.calls[0]?.[0];
    expect(typeof arg).toBe('string');
    expect(arg).toContain('graph LR');
    const custom = screen.getByTestId('custom-render');
    expect(custom.textContent).toContain('graph LR');
    // Default <pre> must not be rendered when renderer is supplied.
    expect(screen.queryByTestId('workflow-visualizer-source')).toBeNull();
  });

  it('exposes buildMermaidSource as a pure callable function', () => {
    const source = buildMermaidSource(
      {
        field: 'state',
        states: { draft: { label: 'Draft' } },
        transitions: [],
      },
      null,
      'LR',
    );
    expect(source).toContain('graph LR');
    expect(source).toContain('draft["Draft"]');
  });

  it('emits inline color style for states declaring CSS-looking color', () => {
    const source = buildMermaidSource(makeDefinition());
    expect(source).toContain('style Paid fill:#10b981');
  });
});
