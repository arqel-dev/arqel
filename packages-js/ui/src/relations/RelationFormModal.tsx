/**
 * `<RelationFormModal>` — create/edit a related record from within a
 * `RelationManagerPanel` tab. Reuses `<FormRenderer>` for the field list
 * (a flat schema built from `relation.fields`, mirroring how
 * `<ActionFormModal>` derives a `FormSchema` from a flat `ActionFormField[]`)
 * and submits via Inertia `router.post`/`router.put`.
 *
 * On success the modal closes and calls `onSuccess` (if given) so the
 * caller can refresh the relation's table — records now come from
 * `RelationManagerPanel`'s own `fetch()` to `RelationController::index()`
 * (Task 13a), not from an Inertia `relations` prop, so a partial reload
 * would no longer do anything useful here; `ResourceEditTabs` uses
 * `onSuccess` to bump that panel's `refreshKey`.
 *
 * **Edit-mode data loading (data-loss fix)**: when `recordId` is given and
 * the caller doesn't pass an explicit `initialValues`, the form used to
 * mount empty and `submit()` would `PUT` those empty values, silently
 * wiping out every untouched column server-side. To prevent that, this
 * component now fetches the current record from
 * `RelationController::edit()` (`GET {base}/{recordId}/edit`, a plain JSON
 * endpoint returning `{ fields, record }`) on mount, mirroring the
 * `fetch()`-on-mount pattern `RelationManagerPanel` already uses against
 * `RelationController::index()`. While that fetch is in flight, or if it
 * fails, submit stays disabled so the modal can never PUT an empty/partial
 * `values` object — the safe default when we don't yet know the real data.
 */

import { useArqelTranslations } from '@arqel-dev/react/utils';
import type { FieldSchema } from '@arqel-dev/types/fields';
import type { FormSchema } from '@arqel-dev/types/forms';
import type { RelationManagerProps } from '@arqel-dev/types/relations';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { FormActions } from '../form/FormActions.js';
import { FormRenderer, type FormRendererProps } from '../form/FormRenderer.js';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '../shadcn/ui/dialog.js';
import { ErrorState } from '../utility/ErrorState.js';
import { LoadingSkeleton } from '../utility/LoadingSkeleton.js';

export interface RelationFormModalProps {
  open: boolean;
  onClose(): void;
  relation: RelationManagerProps;
  parentSlug: string;
  parentId: string | number;
  basePath?: string;
  recordId?: string | number;
  initialValues?: Record<string, unknown>;
  onSuccess?: () => void;
}

export function RelationFormModal({
  open,
  onClose,
  relation,
  parentSlug,
  parentId,
  basePath = '/admin',
  recordId,
  initialValues,
  onSuccess,
}: RelationFormModalProps) {
  const t = useArqelTranslations();
  // When editing without an explicit `initialValues`, the record isn't
  // known yet — start empty but keep the form gated (see `canSubmit` below)
  // until the fetch below resolves.
  const [values, setValues] = useState<Record<string, unknown>>(initialValues ?? {});
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [processing, setProcessing] = useState(false);

  const needsFetch = recordId !== undefined && initialValues === undefined;
  const [loadingRecord, setLoadingRecord] = useState(needsFetch);
  const [loadError, setLoadError] = useState(false);

  const base = `${basePath}/${parentSlug}/${parentId}/relations/${relation.slug}`;

  // biome-ignore lint/correctness/useExhaustiveDependencies: `open` is a deliberate re-fetch trigger (refetch each time the modal is reopened for a given record), not a value the effect reads.
  useEffect(() => {
    if (!open || !needsFetch) return;
    let cancelled = false;
    const editUrl = `${base}/${recordId}/edit`;

    const doFetch = async () => {
      setLoadingRecord(true);
      setLoadError(false);
      try {
        const response = await fetch(editUrl, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(`Request failed with status ${response.status}`);
        const body = (await response.json()) as { record?: Record<string, unknown> };
        if (!cancelled && body.record) {
          setValues(body.record);
        } else if (!cancelled) {
          throw new Error('Response did not include a record');
        }
      } catch {
        // Leave `values` empty and keep the form gated — never let a failed
        // fetch fall through to a submit that would PUT empty data.
        if (!cancelled) setLoadError(true);
      } finally {
        if (!cancelled) setLoadingRecord(false);
      }
    };

    void doFetch();

    return () => {
      cancelled = true;
    };
  }, [open, needsFetch, base, recordId]);

  const fields = relation.fields as FieldSchema[];

  const schema: FormSchema = useMemo(
    () => ({
      schema: fields.map((field) => ({
        kind: 'field' as const,
        name: field.name,
        type: field.type,
      })),
      columns: 1,
      model: null,
      inline: false,
      disabled: false,
    }),
    [fields],
  );

  if (!open) return null;

  const url = recordId ? `${base}/${recordId}` : base;
  const method = recordId ? 'put' : 'post';

  // Never allow a submit while the current record's data is still loading
  // or failed to load — that's exactly the empty-`values` PUT that caused
  // the data-loss bug this component now guards against.
  const blockedByRecordLoad = needsFetch && (loadingRecord || loadError);

  const submit = () => {
    if (blockedByRecordLoad) return;
    setProcessing(true);
    router[method](url, values as Record<string, never>, {
      preserveScroll: true,
      onSuccess: () => {
        setProcessing(false);
        onSuccess?.();
        onClose();
      },
      onError: (formErrors: Record<string, string>) => {
        setProcessing(false);
        setErrors(
          Object.fromEntries(Object.entries(formErrors).map(([key, message]) => [key, [message]])),
        );
      },
    });
  };

  // `FormRenderer` itself is submit-agnostic (state lives in the caller);
  // the wrapping `<form onSubmit>` below drives real submission, mirroring
  // `ArqelEditPage`/`ActionFormModal`. The stray `onSubmit` in this typed
  // props bag is inert for the real component but lets a test double
  // (which renders a plain submit button) exercise the same `submit`.
  const formRendererProps: FormRendererProps & { onSubmit: () => void } = {
    schema,
    fields,
    values,
    errors,
    onChange: (name, value) => setValues((prev) => ({ ...prev, [name]: value })),
    disabled: processing || blockedByRecordLoad,
    onSubmit: submit,
  };

  return (
    <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
      <DialogContent className="overflow-auto sm:max-w-[32rem]">
        <DialogHeader>
          <DialogTitle>{relation.label}</DialogTitle>
        </DialogHeader>
        {needsFetch && loadingRecord ? (
          <LoadingSkeleton variant="block" count={3} />
        ) : needsFetch && loadError ? (
          <ErrorState
            title={t('relations.form.loadError', "Couldn't load this record")}
            description={t('relations.form.loadErrorDescription', 'Please try again.')}
          />
        ) : null}
        <form
          className="flex flex-col gap-4"
          onSubmit={(e) => {
            e.preventDefault();
            submit();
          }}
        >
          <FormRenderer {...formRendererProps} />
          <FormActions
            processing={processing}
            disabled={blockedByRecordLoad}
            submitLabel={t('form.save', 'Save')}
            onCancel={onClose}
          />
        </form>
      </DialogContent>
    </Dialog>
  );
}
