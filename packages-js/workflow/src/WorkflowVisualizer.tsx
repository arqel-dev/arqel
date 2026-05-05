/**
 * `<WorkflowVisualizer>` — apresentational component that turns a
 * workflow definition into a Mermaid `graph` source string.
 *
 * Render contract (from WF-005):
 *   - generates a pure Mermaid source via `buildMermaidSource(...)`,
 *   - by default renders `<pre className="language-mermaid">{source}</pre>`
 *     so consumers that already have mermaid configured can transform
 *     the block into an SVG,
 *   - optionally accepts a `renderer` prop receiving the source string
 *     and returning a `ReactNode` (typically an SVG produced by the
 *     consumer's own mermaid runtime).
 *
 * The component does **not** ship the mermaid runtime — keeping the
 * package dependency-free. The renderer prop is the extension point.
 */

import { Card, CardContent } from '@arqel-dev/ui';
import type { ReactElement, ReactNode } from 'react';

export interface WorkflowVisualizerStateShape {
  label: string;
  color?: string | null;
  icon?: string | null;
}

export interface WorkflowVisualizerTransitionShape {
  from: string | string[] | null;
  to: string;
  label?: string;
}

export interface WorkflowDefinitionShape {
  field: string;
  states: Record<string, WorkflowVisualizerStateShape>;
  transitions: WorkflowVisualizerTransitionShape[];
}

export type WorkflowVisualizerDirection = 'LR' | 'TB';

export interface WorkflowVisualizerProps {
  definition: WorkflowDefinitionShape;
  currentState?: string | null;
  renderer?: (mermaidSource: string) => ReactNode;
  direction?: WorkflowVisualizerDirection;
  className?: string;
}

/**
 * Maps a state key (which can be an FQCN like `App\\States\\Pending`)
 * into a short slug suitable as a Mermaid node id. We take the last
 * segment after `\` or `/`, lowercase non-alphanum runs, and strip
 * common state suffixes.
 */
export function slugifyStateId(key: string): string {
  const lastSegment = key.replace(/\\\\/g, '\\').split(/[\\/]/).pop() ?? key;
  const stripped = lastSegment.replace(/State$|Status$/, '');
  const safe = stripped.replace(/[^A-Za-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
  return safe.length > 0 ? safe : 'state';
}

/**
 * Returns a Mermaid-friendly node label, escaping double quotes.
 */
function quoteLabel(label: string): string {
  return label.replace(/"/g, '#quot;');
}

/**
 * Infers a transition label from a FQCN-like string when no explicit
 * label is provided. `App\\Transitions\\PayOrder` → `pay order`.
 */
function inferTransitionLabel(raw: string | undefined, fallback: string): string {
  if (raw && raw.length > 0) {
    const tail = raw.replace(/\\\\/g, '\\').split(/[\\/]/).pop() ?? raw;
    const stripped = tail.replace(/Transition$|State$|Status$/, '');
    if (stripped.length === 0) return fallback;
    // Insert spaces between camelCase boundaries and lowercase.
    return stripped
      .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
      .replace(/[_-]+/g, ' ')
      .toLowerCase()
      .trim();
  }
  return fallback;
}

/**
 * Pure function — builds a Mermaid source string from a workflow
 * definition. Exported for direct testing and for consumers that want
 * the source independent of React.
 */
export function buildMermaidSource(
  definition: WorkflowDefinitionShape,
  currentState: string | null = null,
  direction: WorkflowVisualizerDirection = 'LR',
): string {
  const stateKeys = Object.keys(definition.states);
  const idMap = new Map<string, string>();
  const usedIds = new Set<string>();

  for (const key of stateKeys) {
    let candidate = slugifyStateId(key);
    let suffix = 1;
    while (usedIds.has(candidate)) {
      suffix += 1;
      candidate = `${slugifyStateId(key)}_${suffix}`;
    }
    usedIds.add(candidate);
    idMap.set(key, candidate);
  }

  const lines: string[] = [`graph ${direction}`];

  // Node declarations.
  for (const key of stateKeys) {
    const state = definition.states[key];
    if (state === undefined) continue;
    const id = idMap.get(key) ?? slugifyStateId(key);
    lines.push(`  ${id}["${quoteLabel(state.label)}"]`);
  }

  // Edge declarations.
  for (const transition of definition.transitions) {
    const toId = idMap.get(transition.to);
    if (toId === undefined) continue;

    const label = inferTransitionLabel(transition.label, '');
    const edge = label.length > 0 ? `-->|${label}|` : '-->';

    let froms: string[];
    if (transition.from === null || transition.from === undefined) {
      froms = stateKeys;
    } else if (Array.isArray(transition.from)) {
      froms = transition.from;
    } else {
      froms = [transition.from];
    }

    for (const from of froms) {
      const fromId = idMap.get(from);
      if (fromId === undefined) continue;
      lines.push(`  ${fromId} ${edge} ${toId}`);
    }
  }

  // Inline color styles for states that declare a CSS-looking colour.
  for (const key of stateKeys) {
    const state = definition.states[key];
    if (state === undefined) continue;
    const color = state.color;
    if (typeof color === 'string' && color.trim().length > 0) {
      const id = idMap.get(key) ?? slugifyStateId(key);
      lines.push(`  style ${id} fill:${color.trim()}`);
    }
  }

  // Highlight current state on top of any user-declared style.
  if (currentState !== null && currentState !== undefined) {
    const currentId = idMap.get(currentState);
    if (currentId !== undefined) {
      lines.push(`  style ${currentId} fill:#fbbf24,stroke:#d97706,stroke-width:2px`);
    }
  }

  return `${lines.join('\n')}\n`;
}

export function WorkflowVisualizer({
  definition,
  currentState = null,
  renderer,
  direction = 'LR',
  className,
}: WorkflowVisualizerProps): ReactElement {
  const source = buildMermaidSource(definition, currentState, direction);
  const wrapperClass = className ?? 'arqel-workflow-visualizer';

  if (renderer) {
    return (
      <Card
        className={wrapperClass}
        data-testid="workflow-visualizer"
        data-field={definition.field}
      >
        <CardContent className="p-4">{renderer(source)}</CardContent>
      </Card>
    );
  }

  return (
    <Card className={wrapperClass} data-testid="workflow-visualizer" data-field={definition.field}>
      <CardContent className="p-4">
        <pre
          className="language-mermaid overflow-x-auto rounded-md bg-muted p-3 text-sm text-foreground"
          data-testid="workflow-visualizer-source"
        >
          {source}
        </pre>
      </CardContent>
    </Card>
  );
}

export default WorkflowVisualizer;
