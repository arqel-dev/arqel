import { Link } from '@inertiajs/react';
import type { Plugin } from '../../types';

type Props = {
  plugin: Plugin;
};

function formatInstallCount(count: number): string {
  if (count >= 1_000_000) return `${(count / 1_000_000).toFixed(1)}M`;
  if (count >= 1_000) return `${(count / 1_000).toFixed(1)}k`;
  return String(count);
}

export function PluginCard({ plugin }: Props): JSX.Element {
  return (
    <article className="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm transition hover:shadow-md">
      <Link href={`/plugins/${plugin.slug}`} className="block">
        <header className="flex items-start justify-between gap-2">
          <h3 className="text-base font-semibold text-neutral-900">{plugin.name}</h3>
          <span
            data-testid="type-badge"
            className="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800"
          >
            {plugin.type}
          </span>
        </header>
        <p className="mt-2 line-clamp-2 text-sm text-neutral-600">{plugin.description}</p>
        <footer className="mt-3 flex items-center gap-3 text-xs text-neutral-500">
          <span data-testid="install-count">
            {formatInstallCount(plugin.install_count ?? 0)} installs
          </span>
          {typeof plugin.stars === 'number' && <span data-testid="stars">⭐ {plugin.stars}</span>}
        </footer>
      </Link>
    </article>
  );
}
