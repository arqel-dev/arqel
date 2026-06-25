import type { Version } from '@arqel-dev/versioning';
import type { JSX } from 'react';
import { VersionHistoryDrawer } from '../Components/VersionHistoryDrawer';

/**
 * Demo surface (GET /admin/versions-demo) that mounts VersionHistoryDrawer so
 * the Phase-5 versioning E2E can reach the @arqel-dev/versioning timeline/diff
 * components. The versions are hardcoded to a stable shape: one initial
 * (non-restorable) and one regular edit (restorable). Standalone — it only
 * needs `data-testid="version-history-drawer"` visible after login.
 */
const versions: Version[] = [
  {
    id: 2,
    created_at: '2026-06-08T12:00:00Z',
    changes_summary: 'Updated title and body',
    user: { id: 1, name: 'Admin' },
    is_initial: false,
  },
  {
    id: 1,
    created_at: '2026-06-08T10:00:00Z',
    changes_summary: 'Initial version',
    user: { id: 1, name: 'Admin' },
    is_initial: true,
  },
];

export default function VersionsDemo(): JSX.Element {
  return (
    <main id="arqel-main">
      <h1 className="mb-4 text-xl font-semibold">Version History Demo</h1>
      <VersionHistoryDrawer resource="posts" id={1} versions={versions} />
    </main>
  );
}
