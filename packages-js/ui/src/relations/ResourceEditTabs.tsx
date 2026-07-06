/**
 * `<ResourceEditTabs>` — page-level tabs wrapping an edit page: a "Data"
 * tab (the existing form, passed as `children`) plus one tab per declared
 * `RelationManager`. Zero regression is the hard requirement: when
 * `relations` is empty (the overwhelming majority of Resources, which
 * declare no relation managers), this renders `children` directly with no
 * `Tabs` wrapper at all — so `ArqelEditPage`'s existing DOM/behavior is
 * byte-for-byte unchanged for every Resource that hasn't opted in.
 *
 * The active tab is deep-linked via `?tab=slug` (read once on mount from
 * `window.location.search`) so a reload / shared link lands back on the
 * same relation tab; navigating tabs updates the query string in place
 * (no Inertia visit — this is pure client-side tab state, not a page nav).
 *
 * Records source: `RelationManager::toArray()` (the server payload backing
 * each entry in `relations`) deliberately does NOT include the related
 * records — only `slug`/`label`/`type`/`table`/`fields`/`abilities`
 * (see `arqel-dev/types`' `RelationManagerProps` docblock). The actual rows
 * live behind `RelationController::index()`
 * (`GET {resource}/{parent}/relations/{relation}`), a plain JSON endpoint
 * (not an Inertia partial reload — it returns `{ records, table, abilities
 * }` directly), so each panel fetches its own records on mount via `fetch`.
 * This keeps `ResourceEditTabs` from needing to own a records cache itself;
 * a follow-up ticket can hoist this into a shared hook if multiple
 * consumers need the same relation's records.
 *
 * Labels are a literal English fallback ("Data") rather than routed
 * through `useArqelTranslations`: that hook resolves its dictionary via
 * `usePage()`, and this component (like `RelationManagerPanel`, see its
 * docblock) is exercised directly in unit tests without a full Inertia
 * page context. `ArqelEditPage` itself already sits inside that context
 * and can localize its own strings; the tab-shell chrome added here
 * stays framework-agnostic.
 */

import type { RelationManagerProps } from '@arqel-dev/types/relations';
import { type ReactNode, useState } from 'react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../shadcn/ui/tabs.js';
import { AttachModal } from './AttachModal.js';
import { RelationFormModal } from './RelationFormModal.js';
import { RelationManagerPanel } from './RelationManagerPanel.js';

export interface ResourceEditTabsProps {
  relations: RelationManagerProps[];
  parentSlug: string;
  parentId: string | number;
  basePath?: string;
  children: ReactNode;
}

function initialTab(relations: RelationManagerProps[]): string {
  const fromQuery = new URLSearchParams(window.location.search).get('tab');
  if (fromQuery && (fromQuery === 'data' || relations.some((r) => r.slug === fromQuery))) {
    return fromQuery;
  }
  return 'data';
}

export function ResourceEditTabs({
  relations,
  parentSlug,
  parentId,
  basePath = '/admin',
  children,
}: ResourceEditTabsProps) {
  // Hooks below only ever run when `relations` is non-empty in practice,
  // but React requires them unconditionally — cheap for the empty case.
  const [tab, setTab] = useState(() => initialTab(relations));
  const [modal, setModal] = useState<{ slug: string; recordId?: string | number } | null>(null);
  const [attachSlug, setAttachSlug] = useState<string | null>(null);

  if (relations.length === 0) return <>{children}</>;

  const setActiveTab = (next: string) => {
    setTab(next);
    const url = new URL(window.location.href);
    if (next === 'data') {
      url.searchParams.delete('tab');
    } else {
      url.searchParams.set('tab', next);
    }
    window.history.replaceState(window.history.state, '', url);
  };

  const activeRelation = relations.find((r) => r.slug === modal?.slug);
  const attachRelation = relations.find((r) => r.slug === attachSlug);

  return (
    <Tabs value={tab} onValueChange={setActiveTab}>
      <TabsList>
        <TabsTrigger value="data">Data</TabsTrigger>
        {relations.map((relation) => (
          <TabsTrigger key={relation.slug} value={relation.slug}>
            {relation.label}
          </TabsTrigger>
        ))}
      </TabsList>
      <TabsContent value="data">{children}</TabsContent>
      {relations.map((relation) => (
        <TabsContent key={relation.slug} value={relation.slug}>
          <RelationManagerPanel
            relation={relation}
            parentSlug={parentSlug}
            parentId={parentId}
            records={[]}
            onCreate={() => setModal({ slug: relation.slug })}
            onEdit={(id) => setModal({ slug: relation.slug, recordId: id })}
            onAttach={() => setAttachSlug(relation.slug)}
          />
        </TabsContent>
      ))}
      {modal && activeRelation && (
        <RelationFormModal
          open
          onClose={() => setModal(null)}
          relation={activeRelation}
          parentSlug={parentSlug}
          parentId={parentId}
          basePath={basePath}
          {...(modal.recordId !== undefined ? { recordId: modal.recordId } : {})}
        />
      )}
      {attachSlug && attachRelation && (
        <AttachModal
          open
          onClose={() => setAttachSlug(null)}
          relation={attachRelation}
          parentSlug={parentSlug}
          parentId={parentId}
          basePath={basePath}
        />
      )}
    </Tabs>
  );
}
