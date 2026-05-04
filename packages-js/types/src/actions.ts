/**
 * Action schema mirroring `arqel-dev/actions` PHP serialisation.
 *
 * The shape is the output of `Action::toArray()` after PHP's
 * `array_filter(... !== null)` — so optional fields are simply
 * absent from the payload rather than `null`.
 */

export type ActionType = 'row' | 'bulk' | 'toolbar' | 'header';

export type ActionColor = 'primary' | 'secondary' | 'destructive' | 'success' | 'warning' | 'info';

export type ActionVariant = 'default' | 'outline' | 'ghost' | 'destructive';

export type ActionMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export type ConfirmationColor = 'destructive' | 'warning' | 'info';

/**
 * Modal config emitted when an Action requires confirmation.
 */
export interface ConfirmationConfig {
  /** Modal heading. */
  heading?: string;
  /** Optional explanation rendered under the heading. */
  description?: string;
  /** Heroicon name. */
  icon?: string;
  /** Visual variant. Defaults to `destructive`. */
  color?: ConfirmationColor;
  /** Force the user to type this exact text before confirming. */
  requiresText?: string;
  /** Override for the submit button label. */
  submitLabel?: string;
  /** Override for the cancel button label. */
  cancelLabel?: string;
}

export type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | 'full';

/**
 * Field schema entry used by Action form modals (see `arqel-dev/actions`
 * `HasForm`). Action form fields are intentionally a flat list —
 * layout components live in regular `Form` schemas.
 */
export interface ActionFormField {
  name: string;
  type: string;
}

/**
 * Action shape consumed by the React renderer (`<ActionButton>`,
 * `<ActionMenu>`, `<ActionFormModal>`).
 */
export interface ActionSchema {
  name: string;
  type: ActionType;
  label: string;
  icon?: string;
  color: ActionColor;
  variant: ActionVariant;
  method: ActionMethod;
  /** Resolved URL for link-mode actions; absent for callbacks. */
  url?: string;
  tooltip?: string;
  /** True when the action is disabled per-record. */
  disabled?: true;
  /** True when the action requires confirmation. */
  requiresConfirmation?: true;
  /** Modal config; absent when confirmation is not required. */
  confirmation?: ConfirmationConfig;
  /** Form modal field schema; absent when the action has no form. */
  form?: ActionFormField[];
  /** Modal size for form actions. */
  modalSize?: ModalSize;
  /** Flash message shown on successful execution. */
  successNotification?: string;
  /** Flash message shown when the callback throws. */
  failureNotification?: string;
}

/**
 * Server-side action collections for a Resource (`Resource::actions`,
 * `bulkActions`, `toolbarActions`, `headerActions`).
 */
export interface ResourceActions {
  row: ActionSchema[];
  bulk: ActionSchema[];
  toolbar: ActionSchema[];
}
