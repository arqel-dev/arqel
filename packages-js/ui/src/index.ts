/**
 * @arqel/ui — Structural React components for Arqel admin panels.
 *
 * Top-level barrel re-exports the most-used primitives. Subpath imports
 * (`@arqel/ui/action`, `@arqel/ui/auth`) are recommended for tree-shaking.
 */

export type { ActionButtonProps } from './action/ActionButton.js';
export { ActionButton } from './action/ActionButton.js';
export type { ActionFormModalProps } from './action/ActionFormModal.js';
export { ActionFormModal } from './action/ActionFormModal.js';
export type { ActionMenuProps } from './action/ActionMenu.js';
export { ActionMenu } from './action/ActionMenu.js';
export type { ButtonProps } from './action/Button.js';
export { Button, buttonVariants } from './action/Button.js';
export type { ConfirmDialogProps } from './action/ConfirmDialog.js';
export { ConfirmDialog } from './action/ConfirmDialog.js';
export type { CanAccessProps } from './auth/CanAccess.js';
export { CanAccess } from './auth/CanAccess.js';
export type { FlashContainerProps, FlashPosition } from './flash/FlashContainer.js';
export { FlashContainer } from './flash/FlashContainer.js';
export type { FlashKind, FlashToastProps } from './flash/FlashToast.js';
export { FlashToast } from './flash/FlashToast.js';
export {
  clearFieldRegistry,
  type FieldComponent,
  getFieldComponent,
  registerField,
} from './form/FieldRegistry.js';
export type { FieldRendererProps } from './form/FieldRenderer.js';
export { FieldRenderer } from './form/FieldRenderer.js';
export type { FormActionsProps } from './form/FormActions.js';
export { FormActions } from './form/FormActions.js';
export type { FormRendererProps } from './form/FormRenderer.js';
export { FormRenderer } from './form/FormRenderer.js';
export type { CommandPaletteProps, PaletteCommand } from './palette/CommandPalette.js';
export { CommandPalette } from './palette/CommandPalette.js';
export type { ResourceIndexUIProps } from './resource/ResourceIndex.js';
export { ResourceIndex } from './resource/ResourceIndex.js';
export type { AppShellProps, AppShellVariant } from './shell/AppShell.js';
export { AppShell } from './shell/AppShell.js';
export type { FooterProps } from './shell/Footer.js';
export { Footer } from './shell/Footer.js';
export type { MainContentMaxWidth, MainContentProps } from './shell/MainContent.js';
export { MainContent } from './shell/MainContent.js';
export type { SidebarProps } from './shell/Sidebar.js';
export { Sidebar } from './shell/Sidebar.js';
export type { TopbarProps } from './shell/Topbar.js';
export { Topbar } from './shell/Topbar.js';
export type { DataTableProps, DataTableRecord } from './table/DataTable.js';
export { DataTable } from './table/DataTable.js';
export type { TableFiltersProps } from './table/TableFilters.js';
export { TableFilters } from './table/TableFilters.js';
export type { TablePaginationProps } from './table/TablePagination.js';
export { TablePagination } from './table/TablePagination.js';
export type { TableToolbarProps } from './table/TableToolbar.js';
export { TableToolbar } from './table/TableToolbar.js';
export type { BreadcrumbItem, BreadcrumbsProps } from './utility/Breadcrumbs.js';
export { Breadcrumbs } from './utility/Breadcrumbs.js';
export type { EmptyStateProps } from './utility/EmptyState.js';
export { EmptyState } from './utility/EmptyState.js';
export type { ErrorStateProps } from './utility/ErrorState.js';
export { ErrorState } from './utility/ErrorState.js';
export type { LoadingSkeletonProps, SkeletonVariant } from './utility/LoadingSkeleton.js';
export { LoadingSkeleton } from './utility/LoadingSkeleton.js';
export type { PageHeaderProps } from './utility/PageHeader.js';
export { PageHeader } from './utility/PageHeader.js';
export { cn } from './utils/cn.js';
export type { StatCardColor, StatCardProps, StatCardWidget } from './widgets/StatCard.js';
export { StatCard } from './widgets/StatCard.js';
export type { WidgetWrapperProps } from './widgets/WidgetWrapper.js';
export { WidgetWrapper } from './widgets/WidgetWrapper.js';
