import { Head, Link } from '@inertiajs/react';
import { PluginList } from '../../Components/Marketplace/PluginList';
import type { Plugin, PluginCategory } from '../../types';

type Props = {
  featured: Plugin[];
  trending: Plugin[];
  newPlugins: Plugin[];
  categories: PluginCategory[];
};

export default function Landing({
  featured,
  trending,
  newPlugins,
  categories,
}: Props): JSX.Element {
  return (
    <>
      <Head title="Arqel Marketplace" />
      <main className="mx-auto max-w-7xl px-4 py-12">
        <section className="mb-12 text-center">
          <h1 className="text-4xl font-bold tracking-tight text-neutral-900">Arqel Marketplace</h1>
          <p className="mt-4 text-lg text-neutral-600">
            Descubra plugins community para estender seu admin panel Arqel.
          </p>
          <Link
            href="/browse"
            className="mt-6 inline-block rounded-lg bg-blue-600 px-6 py-3 text-sm font-medium text-white hover:bg-blue-700"
          >
            Explorar plugins
          </Link>
        </section>

        <Section title="Featured" plugins={featured} />
        <Section title="Trending" plugins={trending} />
        <Section title="Novos esta semana" plugins={newPlugins} />

        <section className="mt-12">
          <h2 className="mb-4 text-2xl font-semibold">Categorias</h2>
          <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
            {categories.map((c) => (
              <Link
                key={c.id}
                href={`/browse?category=${c.slug}`}
                className="rounded-lg border border-neutral-200 bg-white p-4 text-center text-sm font-medium hover:border-blue-400"
              >
                {c.name}
              </Link>
            ))}
          </div>
        </section>
      </main>
    </>
  );
}

function Section({ title, plugins }: { title: string; plugins: Plugin[] }): JSX.Element {
  return (
    <section className="mb-10">
      <h2 className="mb-4 text-2xl font-semibold">{title}</h2>
      <PluginList plugins={plugins} emptyMessage={`Nenhum plugin em ${title.toLowerCase()}.`} />
    </section>
  );
}
