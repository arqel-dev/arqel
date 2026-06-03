/**
 * Tenant context types — mirror of Arqel\Tenant\Http\Controllers\TenantSwitcherController::serialise.
 */
export interface TenantSummary {
  id: string | number;
  name: string | null;
  slug: string | null;
  logo: string | null;
}

export interface TenantContextProps {
  current: TenantSummary | null;
  available: ReadonlyArray<TenantSummary>;
}
