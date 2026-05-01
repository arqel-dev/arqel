import { useCompareSlugs } from '../../hooks/useCompareSlugs';

export function CompareFloatingBar(): JSX.Element | null {
  const { slugs, removeSlug, clear } = useCompareSlugs();

  if (slugs.length === 0) return null;

  const href = `/compare?slugs=${slugs.join(',')}`;
  const canCompare = slugs.length >= 2;

  return (
    <div
      data-testid="compare-floating-bar"
      className="fixed inset-x-0 bottom-0 z-40 border-t border-neutral-200 bg-white shadow-lg"
    >
      <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div className="flex flex-wrap items-center gap-2 text-sm">
          <span className="font-medium text-neutral-700">Comparar ({slugs.length}/3):</span>
          {slugs.map((slug) => (
            <span
              key={slug}
              data-testid={`compare-chip-${slug}`}
              className="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2 py-1 text-xs"
            >
              {slug}
              <button
                type="button"
                onClick={() => removeSlug(slug)}
                aria-label={`Remover ${slug}`}
                className="text-neutral-500 hover:text-red-600"
              >
                ×
              </button>
            </span>
          ))}
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={clear}
            data-testid="compare-clear"
            className="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100"
          >
            Limpar
          </button>
          <a
            href={canCompare ? href : undefined}
            data-testid="compare-now"
            aria-disabled={!canCompare}
            className={`rounded px-3 py-1.5 text-sm font-medium ${
              canCompare
                ? 'bg-blue-600 text-white hover:bg-blue-700'
                : 'cursor-not-allowed bg-neutral-200 text-neutral-500'
            }`}
          >
            Comparar agora
          </a>
        </div>
      </div>
    </div>
  );
}
