/**
 * Default Inertia page for `arqel::index`.
 *
 * Pulls every prop the server emits via `InertiaDataBuilder::
 * buildIndexData` / `buildTableIndexData` and forwards them to
 * `<ResourceIndex>`. Apps can override per-resource by registering
 * their own page component at `Pages/Arqel/{Slug}/Index.tsx` (the
 * lookup falls through to user pages first inside `createArqelApp`).
 */

import type { RecordType, ResourceIndexProps } from '@arqel-dev/types/resources';
import { usePage } from '@inertiajs/react';
import type { JSX } from 'react';
import { ResourceIndex } from '../resource/ResourceIndex.js';

export default function ArqelIndexPage<TRecord extends RecordType = RecordType>(): JSX.Element {
  const page = usePage();
  const props = page.props as unknown as ResourceIndexProps<TRecord>;

  return <ResourceIndex<TRecord> {...props} />;
}
