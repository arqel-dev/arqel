import { router } from '@inertiajs/react';
import { CategoryFilter } from '../../Components/Marketplace/CategoryFilter';
import { MetaTags } from '../../Components/Marketplace/MetaTags';
import { PluginList } from '../../Components/Marketplace/PluginList';
import type { Paginator, Plugin, PluginCategory } from '../../types';

type Props = {
  plugins: Paginator<Plugin>;
  categories: PluginCategory[];
  filters: { type: string | null; category: string | null };
};

export default function Browse({ plugins, categories, filters }: Props): JSX.Element {
  function selectCategory(slug: string | null): void {
    router.get(
      '/browse',
      { ...filters, category: slug },
      { preserveState: false, preserveScroll: true },
    );
  }

  function selectType(type: string | null): void {
    router.get('/browse', { ...filters, type }, { preserveState: false, preserveScroll: true });
  }

  const types = ['field', 'widget', 'integration', 'theme'];

  return (
    <>
      <MetaTags
        title="Browse plugins — Arqel Marketplace"
        description={
          filters.category !== null
            ? `Browse plugins na categoria ${filters.category} — Arqel Marketplace.`
            : filters.type !== null
              ? `Browse plugins do tipo ${filters.type} — Arqel Marketplace.`
              : 'Browse todos os plugins disponíveis no Arqel Marketplace — fields, widgets, integrações e themes.'
        }
      />
      <main className="mx-auto max-w-7xl px-4 py-12">
        <h1 className="mb-8 text-3xl font-bold">Explorar plugins</h1>
        <div className="grid grid-cols-1 gap-8 md:grid-cols-[220px_1fr]">
          <aside>
            <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-neutral-500">
              Tipo
            </h2>
            <div className="mb-6 space-y-1">
              <button
                type="button"
                onClick={() => selectType(null)}
                className={`block w-full rounded px-3 py-2 text-left text-sm ${
                  filters.type === null
                    ? 'bg-blue-100 font-medium text-blue-900'
                    : 'hover:bg-neutral-100'
                }`}
              >
                Todos
              </button>
              {types.map((t) => (
                <button
                  key={t}
                  type="button"
                  onClick={() => selectType(t)}
                  className={`block w-full rounded px-3 py-2 text-left text-sm ${
                    filters.type === t
                      ? 'bg-blue-100 font-medium text-blue-900'
                      : 'hover:bg-neutral-100'
                  }`}
                >
                  {t}
                </button>
              ))}
            </div>

            <h2 className="mb-2 text-sm font-semibold uppercase tracking-wide text-neutral-500">
              Categoria
            </h2>
            <CategoryFilter
              categories={categories}
              selected={filters.category}
              onSelect={selectCategory}
            />
          </aside>

          <section>
            <PluginList plugins={plugins.data} />
            {plugins.last_page > 1 && (
              <nav className="mt-8 flex justify-center gap-2 text-sm">
                <span className="text-neutral-500">
                  Página {plugins.current_page} de {plugins.last_page}
                </span>
              </nav>
            )}
          </section>
        </div>
      </main>
    </>
  );
}
