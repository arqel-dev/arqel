/**
 * `@arqel/versioning` — React surface for the Arqel Versioning PHP package.
 *
 * Exporta `<VersionTimeline>` (lista cronológica de versões) e
 * `<VersionDiff>` (comparador side-by-side). Componentes puramente
 * apresentacionais: não fazem fetch nem decisões de autorização.
 *
 * O endpoint canônico que alimenta o timeline é
 * `GET /admin/{resource}/{id}/versions`, retornado via Inertia props
 * pelo PHP package (`arqel/versioning`).
 */

export {
  type DiffEntry,
  type DiffStatus,
  getDiffEntries,
  VersionDiff,
  type VersionDiffProps,
} from './VersionDiff.js';
export {
  formatRelativeTime,
  type Version,
  VersionTimeline,
  type VersionTimelineProps,
  type VersionUser,
} from './VersionTimeline.js';
