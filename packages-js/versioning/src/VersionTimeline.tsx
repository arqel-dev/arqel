/**
 * `<VersionTimeline>` — listagem cronológica de versões de um Resource.
 *
 * Componente puramente apresentacional: recebe a lista pronta (vinda
 * do endpoint `GET /admin/{resource}/{id}/versions` via Inertia props
 * ou hook do consumidor) e renderiza um feed acessível com botões
 * "View" e "Restore". Não faz fetch nem decisões de autorização.
 */

import { Badge, Button, Card, CardContent, CardHeader, LoadingSkeleton } from '@arqel-dev/ui';
import type { JSX } from 'react';

export interface VersionUser {
  id: number;
  name: string;
}

export interface Version {
  id: number;
  created_at: string;
  changes_summary: string;
  user?: VersionUser | null;
  is_initial: boolean;
}

export interface VersionTimelineProps {
  versions: ReadonlyArray<Version>;
  loading?: boolean;
  onViewDiff?: (version: Version) => void;
  onRestore?: (version: Version) => void;
  canRestore?: (version: Version) => boolean;
}

const RTF =
  typeof Intl !== 'undefined' && 'RelativeTimeFormat' in Intl
    ? new Intl.RelativeTimeFormat('en', { numeric: 'auto' })
    : null;

/**
 * Format an ISO date string as a relative time ("2 hours ago").
 * Falls back to the raw string when `Intl.RelativeTimeFormat` is
 * unavailable (very old runtimes / SSR snapshots without polyfill).
 */
export function formatRelativeTime(iso: string, now: Date = new Date()): string {
  const then = new Date(iso);
  if (Number.isNaN(then.getTime())) {
    return iso;
  }
  if (RTF === null) {
    return iso;
  }
  const diffSeconds = Math.round((then.getTime() - now.getTime()) / 1000);
  const absSeconds = Math.abs(diffSeconds);
  const units: Array<{ unit: Intl.RelativeTimeFormatUnit; seconds: number }> = [
    { unit: 'year', seconds: 60 * 60 * 24 * 365 },
    { unit: 'month', seconds: 60 * 60 * 24 * 30 },
    { unit: 'week', seconds: 60 * 60 * 24 * 7 },
    { unit: 'day', seconds: 60 * 60 * 24 },
    { unit: 'hour', seconds: 60 * 60 },
    { unit: 'minute', seconds: 60 },
  ];
  for (const { unit, seconds } of units) {
    if (absSeconds >= seconds) {
      return RTF.format(Math.round(diffSeconds / seconds), unit);
    }
  }
  return RTF.format(diffSeconds, 'second');
}

function getInitials(name: string | undefined | null): string {
  if (name === undefined || name === null || name.trim() === '') {
    return '?';
  }
  const parts = name.trim().split(/\s+/);
  const first = parts[0] ?? '';
  const last = parts.length > 1 ? (parts[parts.length - 1] ?? '') : '';
  const a = first.charAt(0);
  const b = last.charAt(0);
  return `${a}${b}`.toUpperCase() || '?';
}

interface SkeletonItemProps {
  index: number;
}

function SkeletonItem({ index }: SkeletonItemProps): JSX.Element {
  return (
    <li
      key={`skeleton-${index}`}
      className="relative flex gap-4 pl-6"
      aria-hidden="true"
      data-testid="version-timeline-skeleton"
    >
      <LoadingSkeleton variant="circle" />
      <div className="flex-1 flex flex-col gap-2">
        <LoadingSkeleton variant="line" />
        <LoadingSkeleton variant="line" width="60%" />
      </div>
    </li>
  );
}

export function VersionTimeline({
  versions,
  loading = false,
  onViewDiff,
  onRestore,
  canRestore,
}: VersionTimelineProps): JSX.Element {
  if (loading === true) {
    return (
      <ol
        className="flex flex-col gap-4 border-l border-border pl-4"
        role="feed"
        aria-busy="true"
        aria-label="Loading versions"
      >
        <SkeletonItem index={0} />
        <SkeletonItem index={1} />
        <SkeletonItem index={2} />
      </ol>
    );
  }

  if (versions.length === 0) {
    return (
      <Card
        className="text-center text-muted-foreground"
        role="status"
        data-testid="version-timeline-empty"
      >
        <CardContent className="py-8">
          <p>No versions yet.</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <ol
      className="flex flex-col gap-4 border-l border-border pl-4"
      role="feed"
      aria-label="Version history"
    >
      {versions.map((version) => {
        const userName = version.user?.name ?? 'system';
        const initials = getInitials(version.user?.name);
        const relative = formatRelativeTime(version.created_at);
        const restoreVisible = canRestore === undefined ? true : canRestore(version) === true;

        return (
          <li
            key={version.id}
            className="relative"
            aria-label={`Version ${version.id} by ${userName}, ${relative}: ${version.changes_summary}`}
          >
            <Card>
              <CardHeader className="flex flex-row items-center gap-3">
                <div
                  className="flex h-10 w-10 items-center justify-center rounded-full bg-muted text-sm font-medium text-foreground"
                  aria-hidden="true"
                  data-testid={`version-timeline-avatar-${version.id}`}
                >
                  {initials}
                </div>
                <div className="flex-1 flex flex-col gap-1">
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-foreground">{userName}</span>
                    <Badge variant="outline">#{version.id}</Badge>
                    {version.is_initial === true ? (
                      <Badge variant="secondary">Initial</Badge>
                    ) : null}
                  </div>
                  <time dateTime={version.created_at} className="text-xs text-muted-foreground">
                    {relative}
                  </time>
                </div>
              </CardHeader>
              <CardContent className="flex flex-col gap-3">
                <p className="text-sm text-foreground">{version.changes_summary}</p>
                {onViewDiff !== undefined || (onRestore !== undefined && restoreVisible) ? (
                  <div className="flex gap-2">
                    {onViewDiff !== undefined ? (
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                          onViewDiff(version);
                        }}
                      >
                        Compare
                      </Button>
                    ) : null}
                    {onRestore !== undefined && restoreVisible ? (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          onRestore(version);
                        }}
                      >
                        Restore
                      </Button>
                    ) : null}
                  </div>
                ) : null}
              </CardContent>
            </Card>
          </li>
        );
      })}
    </ol>
  );
}

export default VersionTimeline;
