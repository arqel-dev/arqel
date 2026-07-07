/**
 * `<AttachModal>` — attach an existing record to a `belongsToMany`
 * relation from a `RelationManagerPanel` tab.
 *
 * Phase 1 ships a plain id-entry picker rather than reusing
 * `@arqel-dev/fields`' `BelongsToInput`: that component lives in
 * `arqel-dev/fields`, which depends on `@arqel-dev/ui` (registers into its
 * `FieldRegistry`) — importing it back here would be a circular package
 * dependency. `RelationManager::toArray()` also doesn't yet emit a search
 * route for the attach picker (only `RelationController::attach()`'s
 * `{ related, pivot }` contract), so there's nothing to debounce-search
 * against yet. A follow-up ticket can upgrade this to a search combobox
 * once the server side emits `relation.attachSearchRoute` (or similar).
 *
 * On success the modal closes and calls `onSuccess` (if given) so the
 * caller can refresh the relation's table — see `RelationFormModal`'s
 * docblock for why a partial Inertia reload no longer applies (Task 13a).
 */

import { useArqelTranslations } from '@arqel-dev/react/utils';
import type { RelationManagerProps } from '@arqel-dev/types/relations';
import { router } from '@inertiajs/react';
import { useId, useState } from 'react';
import { FormActions } from '../form/FormActions.js';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '../shadcn/ui/dialog.js';

export interface AttachModalProps {
  open: boolean;
  onClose(): void;
  relation: RelationManagerProps;
  parentSlug: string;
  parentId: string | number;
  basePath?: string;
  onSuccess?: () => void;
}

export function AttachModal({
  open,
  onClose,
  relation,
  parentSlug,
  parentId,
  basePath = '/admin',
  onSuccess,
}: AttachModalProps) {
  const t = useArqelTranslations();
  const inputId = useId();
  const [related, setRelated] = useState('');
  const [processing, setProcessing] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  if (!open) return null;

  const url = `${basePath}/${parentSlug}/${parentId}/relations/${relation.slug}/attach`;

  const submit = () => {
    setProcessing(true);
    router.post(
      url,
      { related, pivot: {} },
      {
        preserveScroll: true,
        onSuccess: () => {
          setProcessing(false);
          onSuccess?.();
          onClose();
        },
        onError: (formErrors: Record<string, string>) => {
          setProcessing(false);
          setErrors(formErrors);
        },
      },
    );
  };

  return (
    <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
      <DialogContent className="overflow-auto sm:max-w-[28rem]">
        <DialogHeader>
          <DialogTitle>
            {t('arqel.relations.attach', 'Attach :label', { label: relation.label })}
          </DialogTitle>
        </DialogHeader>
        <form
          className="flex flex-col gap-4"
          onSubmit={(e) => {
            e.preventDefault();
            submit();
          }}
        >
          <div className="flex flex-col gap-1">
            <label htmlFor={inputId} className="text-sm font-medium">
              {relation.label}
            </label>
            <input
              id={inputId}
              type="text"
              className="rounded-sm border border-border bg-background px-3 py-1.5 text-sm"
              value={related}
              disabled={processing}
              aria-invalid={errors['related'] !== undefined || undefined}
              onChange={(e) => setRelated(e.target.value)}
            />
            {errors['related'] && <p className="text-sm text-destructive">{errors['related']}</p>}
          </div>
          <FormActions
            processing={processing}
            submitLabel={t('arqel.relations.attach_action', 'Attach')}
            onCancel={onClose}
          />
        </form>
      </DialogContent>
    </Dialog>
  );
}
