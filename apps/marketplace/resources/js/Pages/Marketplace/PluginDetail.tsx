import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { PluginList } from '../../Components/Marketplace/PluginList';
import { ReviewList } from '../../Components/Marketplace/ReviewList';
import type { Paginator, Plugin, PluginReview, PluginVersion } from '../../types';

type Props = {
  plugin: Plugin;
  versions: PluginVersion[];
  reviews: Paginator<PluginReview>;
  related: Plugin[];
  has_purchase?: boolean;
};

type Tab = 'readme' | 'versions' | 'reviews';

export default function PluginDetail({
  plugin,
  versions,
  reviews,
  related,
  has_purchase = false,
}: Props): JSX.Element {
  const [tab, setTab] = useState<Tab>('readme');

  const installCommand = plugin.composer_package
    ? `composer require ${plugin.composer_package}`
    : plugin.npm_package
      ? `npm install ${plugin.npm_package}`
      : null;

  return (
    <>
      <Head title={`${plugin.name} — Arqel Marketplace`} />
      <main className="mx-auto max-w-7xl px-4 py-12">
        <header className="mb-8 flex items-start justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold">{plugin.name}</h1>
            <p className="mt-2 text-neutral-600">{plugin.description}</p>
            <div className="mt-3 flex gap-2 text-xs">
              <span className="rounded-full bg-blue-100 px-2 py-0.5 text-blue-800">
                {plugin.type}
              </span>
              <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-neutral-700">
                {plugin.license}
              </span>
            </div>
          </div>
        </header>

        <div className="grid grid-cols-1 gap-8 md:grid-cols-[1fr_280px]">
          <section>
            <nav className="mb-4 flex gap-2 border-b border-neutral-200">
              {(['readme', 'versions', 'reviews'] as Tab[]).map((t) => (
                <button
                  key={t}
                  type="button"
                  onClick={() => setTab(t)}
                  className={`px-4 py-2 text-sm font-medium ${
                    tab === t ? 'border-b-2 border-blue-600 text-blue-600' : 'text-neutral-600'
                  }`}
                >
                  {t === 'readme' ? 'README' : t === 'versions' ? 'Versões' : 'Reviews'}
                </button>
              ))}
            </nav>

            {tab === 'readme' && (
              <article className="prose max-w-none">
                <p>{plugin.description}</p>
              </article>
            )}

            {tab === 'versions' && (
              <ul className="space-y-2">
                {versions.map((v) => (
                  <li key={v.id} className="rounded border border-neutral-200 p-3">
                    <strong>{v.version}</strong>
                    {v.released_at && (
                      <span className="ml-2 text-xs text-neutral-500">{v.released_at}</span>
                    )}
                    {v.changelog && <p className="mt-1 text-sm text-neutral-700">{v.changelog}</p>}
                  </li>
                ))}
              </ul>
            )}

            {tab === 'reviews' && <ReviewList reviews={reviews.data} />}
          </section>

          <aside className="space-y-4">
            {plugin.price_cents != null && plugin.price_cents > 0 && !has_purchase && (
              <a
                href={`/checkout/${plugin.slug}`}
                data-testid="buy-now"
                className="block rounded-lg bg-blue-600 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-blue-700"
              >
                Comprar agora
              </a>
            )}
            {plugin.price_cents != null && plugin.price_cents > 0 && has_purchase && (
              <div
                data-testid="owned-badge"
                className="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-center text-sm font-semibold text-green-800"
              >
                Você já tem esse plugin
              </div>
            )}
            {installCommand && (
              <div className="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                <h3 className="mb-2 text-sm font-semibold">Instalação</h3>
                <code className="block break-all rounded bg-neutral-900 px-3 py-2 text-xs text-green-300">
                  {installCommand}
                </code>
              </div>
            )}

            <a
              href={plugin.github_url}
              target="_blank"
              rel="noopener noreferrer"
              className="block rounded-lg border border-neutral-200 bg-white p-4 text-center text-sm font-medium hover:border-blue-400"
            >
              Ver no GitHub →
            </a>

            {related.length > 0 && (
              <div>
                <h3 className="mb-2 text-sm font-semibold">Plugins relacionados</h3>
                <PluginList plugins={related} />
              </div>
            )}
          </aside>
        </div>
      </main>
    </>
  );
}
