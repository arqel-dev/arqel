import type { Plugin } from '../../types';

type Props = {
  plugins: Plugin[];
  onRemove?: (slug: string) => void;
};

type Row = {
  key: string;
  label: string;
  value: (plugin: Plugin) => string;
};

function formatPrice(plugin: Plugin): string {
  const cents = plugin.price_cents ?? 0;
  if (cents === 0) return 'Free';
  const currency = plugin.currency ?? 'USD';
  return `${(cents / 100).toFixed(2)} ${currency}`;
}

const ROWS: Row[] = [
  { key: 'price', label: 'Preço', value: formatPrice },
  { key: 'installs', label: 'Downloads', value: (p) => String(p.install_count ?? 0) },
  { key: 'stars', label: 'Estrelas', value: (p) => String(p.stars ?? 0) },
  { key: 'type', label: 'Tipo', value: (p) => p.type },
  { key: 'compat', label: 'Compat (versão)', value: (p) => p.latest_version ?? '—' },
  { key: 'license', label: 'Licença', value: (p) => p.license ?? '—' },
  { key: 'last_release', label: 'Último release', value: (p) => p.updated_at ?? '—' },
  { key: 'reviews', label: 'Reviews', value: (p) => String(p.reviews_count ?? 0) },
];

function isDifferent(values: string[]): boolean {
  return new Set(values).size > 1;
}

export function CompareTable({ plugins, onRemove }: Props): JSX.Element {
  return (
    <div className="overflow-x-auto" data-testid="compare-table">
      <table className="w-full min-w-[600px] border-collapse text-sm">
        <thead>
          <tr>
            <th className="border-b border-neutral-200 p-3 text-left text-xs uppercase tracking-wide text-neutral-500">
              Atributo
            </th>
            {plugins.map((plugin) => (
              <th
                key={plugin.slug}
                scope="col"
                className="border-b border-neutral-200 p-3 text-left"
                data-testid={`compare-header-${plugin.slug}`}
              >
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <div className="font-semibold text-neutral-900">{plugin.name}</div>
                    <div className="text-xs text-neutral-500">{plugin.slug}</div>
                  </div>
                  {onRemove && (
                    <button
                      type="button"
                      onClick={() => onRemove(plugin.slug)}
                      className="rounded text-xs text-red-600 hover:underline"
                      aria-label={`Remover ${plugin.name} da comparação`}
                    >
                      Remover
                    </button>
                  )}
                </div>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {ROWS.map((row) => {
            const values = plugins.map((p) => row.value(p));
            const differs = isDifferent(values);
            return (
              <tr key={row.key} data-testid={`compare-row-${row.key}`}>
                <th
                  scope="row"
                  className="border-b border-neutral-100 p-3 text-left font-medium text-neutral-700"
                >
                  {row.label}
                </th>
                {plugins.map((plugin, idx) => (
                  <td
                    key={plugin.slug}
                    data-testid={`compare-cell-${row.key}-${plugin.slug}`}
                    data-differs={differs ? 'true' : 'false'}
                    className={`border-b border-neutral-100 p-3 text-neutral-800 ${
                      differs ? 'border-l-2 border-l-amber-400 bg-amber-50' : ''
                    }`}
                  >
                    {values[idx]}
                  </td>
                ))}
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
