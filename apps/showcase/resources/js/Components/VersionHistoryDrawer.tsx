import { type Version, VersionDiff, VersionTimeline } from '@arqel-dev/versioning';
import { router } from '@inertiajs/react';
import { type JSX, useState } from 'react';

interface VersionHistoryDrawerProps {
  resource: string;
  id: number;
  versions: Version[];
}

/**
 * Demo drawer that exercises @arqel-dev/versioning's VersionTimeline and
 * VersionDiff against the showcase PostResource. Restore posts to the real
 * framework restore route; the initial version is never restorable.
 */
export function VersionHistoryDrawer({
  resource,
  id,
  versions,
}: VersionHistoryDrawerProps): JSX.Element {
  const [active, setActive] = useState<Version | null>(null);
  return (
    <div data-testid="version-history-drawer">
      <VersionTimeline
        versions={versions}
        onViewDiff={setActive}
        onRestore={(v) => router.post(`/admin/${resource}/${id}/versions/${v.id}/restore`)}
        canRestore={(v) => !v.is_initial}
      />
      {active && <VersionDiff before={{}} after={{}} />}
    </div>
  );
}
