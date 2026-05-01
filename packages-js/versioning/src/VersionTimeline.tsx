/**
 * `<VersionTimeline>` — listagem cronológica de versões de um Resource.
 *
 * Componente puramente apresentacional: recebe a lista pronta (vinda
 * do endpoint `GET /admin/{resource}/{id}/versions` via Inertia props
 * ou hook do consumidor) e renderiza um feed acessível com botões
 * "View" e "Restore". Não faz fetch nem decisões de autorização.
 */

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
      className="arqel-version-timeline__item arqel-version-timeline__item--skeleton"
      aria-hidden="true"
      data-testid="version-timeline-skeleton"
    >
      <div className="arqel-version-timeline__avatar arqel-skeleton" />
      <div className="arqel-version-timeline__body">
        <div className="arqel-skeleton arqel-skeleton--line" />
        <div className="arqel-skeleton arqel-skeleton--line arqel-skeleton--short" />
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
        className="arqel-version-timeline arqel-version-timeline--loading"
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
      <div
        className="arqel-version-timeline arqel-version-timeline--empty"
        role="status"
        data-testid="version-timeline-empty"
      >
        <p>No versions yet.</p>
      </div>
    );
  }

  return (
    <ol className="arqel-version-timeline" role="feed" aria-label="Version history">
      {versions.map((version) => {
        const userName = version.user?.name ?? 'system';
        const initials = getInitials(version.user?.name);
        const relative = formatRelativeTime(version.created_at);
        const restoreVisible = canRestore === undefined ? true : canRestore(version) === true;

        return (
          <li
            key={version.id}
            className="arqel-version-timeline__item"
            aria-label={`Version ${version.id} by ${userName}, ${relative}: ${version.changes_summary}`}
          >
            <div
              className="arqel-version-timeline__avatar"
              aria-hidden="true"
              data-testid={`version-timeline-avatar-${version.id}`}
            >
              {initials}
            </div>
            <div className="arqel-version-timeline__body">
              <p className="arqel-version-timeline__summary">
                {version.is_initial === true ? <strong>Initial: </strong> : null}
                {version.changes_summary}
              </p>
              <p className="arqel-version-timeline__meta">
                <span className="arqel-version-timeline__user">{userName}</span>
                <span aria-hidden="true"> · </span>
                <time dateTime={version.created_at} className="arqel-version-timeline__time">
                  {relative}
                </time>
              </p>
              <div className="arqel-version-timeline__actions">
                {onViewDiff !== undefined ? (
                  <button
                    type="button"
                    className="arqel-version-timeline__btn arqel-version-timeline__btn--view"
                    onClick={() => {
                      onViewDiff(version);
                    }}
                  >
                    View
                  </button>
                ) : null}
                {onRestore !== undefined && restoreVisible ? (
                  <button
                    type="button"
                    className="arqel-version-timeline__btn arqel-version-timeline__btn--restore"
                    onClick={() => {
                      onRestore(version);
                    }}
                  >
                    Restore
                  </button>
                ) : null}
              </div>
            </div>
          </li>
        );
      })}
    </ol>
  );
}

export default VersionTimeline;
