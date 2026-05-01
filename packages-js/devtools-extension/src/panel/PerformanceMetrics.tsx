/**
 * Performance metrics dashboard (DEVTOOLS-007).
 *
 * Renders four Web Vitals tiles (LCP, INP/FID, CLS, navigation time)
 * with green / yellow / red status badges based on the canonical Web
 * Vitals thresholds. Server-side telemetry (query count + memory) is
 * sourced from the `__devtools` shared prop emitted by `arqel/core` in
 * `local` environment.
 */

export interface PerformanceMetricsValue {
  readonly lcp: number | null;
  readonly inp: number | null;
  readonly fid: number | null;
  readonly cls: number | null;
  readonly navigationTime: number | null;
}

export interface PerformanceMetricsProps {
  readonly metrics: PerformanceMetricsValue;
  /** Server-side counters from the `__devtools` shared prop. */
  readonly server?: {
    readonly queryCount: number;
    readonly memoryUsage: number;
  } | null;
}

type Status = 'good' | 'needs-improvement' | 'poor' | 'unknown';

interface Tile {
  readonly key: string;
  readonly label: string;
  readonly value: number | null;
  readonly status: Status;
  readonly format: (value: number) => string;
  readonly hint: string;
}

export function PerformanceMetrics({ metrics, server = null }: PerformanceMetricsProps) {
  const responsivenessValue = metrics.inp ?? metrics.fid;
  const responsivenessLabel = metrics.inp !== null ? 'INP' : 'FID';

  const tiles: Tile[] = [
    {
      key: 'lcp',
      label: 'LCP',
      value: metrics.lcp,
      status: classifyLcp(metrics.lcp),
      format: formatMs,
      hint: 'Largest Contentful Paint',
    },
    {
      key: 'responsiveness',
      label: responsivenessLabel,
      value: responsivenessValue,
      status: classifyResponsiveness(responsivenessValue),
      format: formatMs,
      hint: 'Interaction latency',
    },
    {
      key: 'cls',
      label: 'CLS',
      value: metrics.cls,
      status: classifyCls(metrics.cls),
      format: (v) => v.toFixed(3),
      hint: 'Cumulative Layout Shift',
    },
    {
      key: 'navigationTime',
      label: 'Navigation',
      value: metrics.navigationTime,
      status: classifyNavigation(metrics.navigationTime),
      format: formatMs,
      hint: 'Initial navigation duration',
    },
  ];

  const allEmpty = tiles.every((tile) => tile.value === null);
  if (allEmpty && !server) {
    return (
      <div data-testid="arqel-performance" className="arqel-performance">
        <p data-testid="performance-empty" className="arqel-perf-empty">
          No performance metrics captured yet. Interact with the page to populate Web Vitals.
        </p>
      </div>
    );
  }

  return (
    <div data-testid="arqel-performance" className="arqel-performance">
      <ul className="arqel-perf-grid" data-testid="performance-grid">
        {tiles.map((tile) => (
          <li
            key={tile.key}
            data-testid="performance-tile"
            data-metric={tile.key}
            data-status={tile.status}
            className={`arqel-perf-tile arqel-perf-tile--${tile.status}`}
          >
            <span className="arqel-perf-tile-label">{tile.label}</span>
            <span className="arqel-perf-tile-value" data-testid="performance-tile-value">
              {tile.value === null ? '—' : tile.format(tile.value)}
            </span>
            <span className="arqel-perf-tile-hint">{tile.hint}</span>
          </li>
        ))}
      </ul>
      {server && (
        <footer className="arqel-perf-server" data-testid="performance-server">
          <div data-testid="performance-query-count">
            <span className="arqel-perf-server-label">Queries</span>
            <span className="arqel-perf-server-value">{server.queryCount}</span>
          </div>
          <div data-testid="performance-memory">
            <span className="arqel-perf-server-label">Memory</span>
            <span className="arqel-perf-server-value">{formatBytes(server.memoryUsage)}</span>
          </div>
        </footer>
      )}
    </div>
  );
}

function formatMs(value: number): string {
  if (value < 1) return `${(value * 1000).toFixed(0)}µs`;
  if (value >= 1000) return `${(value / 1000).toFixed(2)}s`;
  return `${value.toFixed(0)}ms`;
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function classifyLcp(value: number | null): Status {
  if (value === null) return 'unknown';
  if (value <= 2500) return 'good';
  if (value <= 4000) return 'needs-improvement';
  return 'poor';
}

function classifyResponsiveness(value: number | null): Status {
  if (value === null) return 'unknown';
  if (value <= 200) return 'good';
  if (value <= 500) return 'needs-improvement';
  return 'poor';
}

function classifyCls(value: number | null): Status {
  if (value === null) return 'unknown';
  if (value <= 0.1) return 'good';
  if (value <= 0.25) return 'needs-improvement';
  return 'poor';
}

function classifyNavigation(value: number | null): Status {
  if (value === null) return 'unknown';
  if (value <= 1000) return 'good';
  if (value <= 3000) return 'needs-improvement';
  return 'poor';
}
