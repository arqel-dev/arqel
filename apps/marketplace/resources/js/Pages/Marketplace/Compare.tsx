import { router } from '@inertiajs/react';
import { CompareTable } from '../../Components/Marketplace/CompareTable';
import { MetaTags } from '../../Components/Marketplace/MetaTags';
import { useCompareSlugs } from '../../hooks/useCompareSlugs';
import type { Plugin } from '../../types';

type Props = {
  plugins: Plugin[];
  notFound: string[];
};

export default function Compare({ plugins, notFound }: Props): JSX.Element {
  const { removeSlug } = useCompareSlugs();

  function handleRemove(slug: string): void {
    removeSlug(slug);
    const remaining = plugins.filter((p) => p.slug !== slug).map((p) => p.slug);
    if (remaining.length >= 2) {
      router.get('/compare', { slugs: remaining.join(',') }, { preserveState: false });
    } else {
      router.get('/browse');
    }
  }

  return (
    <>
      <MetaTags
        title={
          plugins.length > 0
            ? `Comparar ${plugins.map((p) => p.name).join(' vs ')} — Arqel Marketplace`
            : 'Comparar plugins — Arqel Marketplace'
        }
        description="Comparação side-by-side de plugins do Arqel Marketplace — preço, downloads, estrelas, licença e mais."
      />
      <main className="mx-auto max-w-7xl px-4 py-12">
        <h1 className="mb-2 text-3xl font-bold">Comparar plugins</h1>
        <p className="mb-6 text-sm text-neutral-600">
          Side-by-side de até 3 plugins. Diferenças destacadas em amarelo.
        </p>

        {notFound.length > 0 && (
          <div
            role="alert"
            data-testid="compare-not-found"
            className="mb-6 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900"
          >
            Plugins não encontrados ou não publicados: {notFound.join(', ')}
          </div>
        )}

        {plugins.length === 0 ? (
          <div
            data-testid="compare-empty"
            className="rounded border border-neutral-200 bg-neutral-50 p-8 text-center text-neutral-600"
          >
            Nenhum plugin para comparar. Volte para o{' '}
            <a href="/browse" className="text-blue-600 underline">
              catálogo
            </a>{' '}
            e adicione plugins.
          </div>
        ) : (
          <CompareTable plugins={plugins} onRemove={handleRemove} />
        )}
      </main>
    </>
  );
}
