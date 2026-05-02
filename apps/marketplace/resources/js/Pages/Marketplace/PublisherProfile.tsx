import { MetaTags } from '../../Components/Marketplace/MetaTags';
import { PluginList } from '../../Components/Marketplace/PluginList';
import type { Plugin, Publisher, PublisherStats } from '../../types';

type Props = {
  publisher: Publisher;
  plugins: Plugin[];
  stats: PublisherStats;
};

function formatNumber(value: number): string {
  if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)}M`;
  if (value >= 1_000) return `${(value / 1_000).toFixed(1)}k`;
  return String(value);
}

export default function PublisherProfile({ publisher, plugins, stats }: Props): JSX.Element {
  const initials = publisher.name
    .split(' ')
    .map((word) => word.charAt(0))
    .join('')
    .slice(0, 2)
    .toUpperCase();

  return (
    <>
      <MetaTags
        title={`${publisher.name} — Arqel Marketplace publisher`}
        description={
          publisher.bio !== null && publisher.bio !== undefined && publisher.bio !== ''
            ? publisher.bio.slice(0, 160)
            : `Plugins publicados por ${publisher.name} no Arqel Marketplace.`
        }
        ogImage={
          publisher.avatar_url !== null &&
          publisher.avatar_url !== undefined &&
          publisher.avatar_url !== ''
            ? publisher.avatar_url
            : null
        }
        ogType="profile"
      />
      <main className="mx-auto max-w-5xl px-4 py-8">
        <header className="flex flex-col gap-4 border-b border-neutral-200 pb-6 sm:flex-row sm:items-start">
          {publisher.avatar_url ? (
            <img
              data-testid="profile-avatar"
              src={publisher.avatar_url}
              alt={publisher.name}
              className="h-20 w-20 rounded-full object-cover"
            />
          ) : (
            <div
              data-testid="profile-avatar-fallback"
              className="flex h-20 w-20 items-center justify-center rounded-full bg-neutral-200 text-xl font-semibold text-neutral-700"
            >
              {initials}
            </div>
          )}

          <div className="flex-1">
            <div className="flex items-center gap-2">
              <h1 className="text-2xl font-bold text-neutral-900">{publisher.name}</h1>
              {publisher.verified === true && (
                <span
                  data-testid="profile-verified"
                  role="img"
                  aria-label="Verified publisher"
                  title="Verified publisher"
                  className="rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800"
                >
                  ✓ Verificado
                </span>
              )}
            </div>

            {publisher.bio !== null && publisher.bio !== undefined && publisher.bio !== '' && (
              <p data-testid="profile-bio" className="mt-2 text-sm text-neutral-700">
                {publisher.bio}
              </p>
            )}

            <div data-testid="profile-social" className="mt-3 flex flex-wrap gap-3 text-sm">
              {publisher.website_url !== null &&
                publisher.website_url !== undefined &&
                publisher.website_url !== '' && (
                  <a
                    data-testid="profile-website"
                    href={publisher.website_url}
                    className="text-blue-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    Website
                  </a>
                )}
              {publisher.github_url !== null &&
                publisher.github_url !== undefined &&
                publisher.github_url !== '' && (
                  <a
                    data-testid="profile-github"
                    href={publisher.github_url}
                    className="text-blue-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    GitHub
                  </a>
                )}
              {publisher.twitter_handle !== null &&
                publisher.twitter_handle !== undefined &&
                publisher.twitter_handle !== '' && (
                  <a
                    data-testid="profile-twitter"
                    href={`https://twitter.com/${publisher.twitter_handle}`}
                    className="text-blue-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    @{publisher.twitter_handle}
                  </a>
                )}
            </div>
          </div>
        </header>

        <section data-testid="profile-stats" className="grid grid-cols-1 gap-4 py-6 sm:grid-cols-3">
          <div className="rounded border border-neutral-200 p-4">
            <p className="text-xs uppercase text-neutral-500">Plugins</p>
            <p data-testid="stat-plugins" className="mt-1 text-2xl font-semibold text-neutral-900">
              {stats.plugins_count}
            </p>
          </div>
          <div className="rounded border border-neutral-200 p-4">
            <p className="text-xs uppercase text-neutral-500">Downloads</p>
            <p
              data-testid="stat-downloads"
              className="mt-1 text-2xl font-semibold text-neutral-900"
            >
              {formatNumber(stats.total_downloads)}
            </p>
          </div>
          <div className="rounded border border-neutral-200 p-4">
            <p className="text-xs uppercase text-neutral-500">Rating médio</p>
            <p data-testid="stat-rating" className="mt-1 text-2xl font-semibold text-neutral-900">
              {stats.avg_rating.toFixed(1)} ★
            </p>
          </div>
        </section>

        <section>
          <h2 className="text-lg font-semibold text-neutral-900">Plugins publicados</h2>
          <div className="mt-4">
            {plugins.length === 0 ? (
              <p data-testid="profile-empty" className="text-sm text-neutral-500">
                Este publisher ainda não tem plugins publicados.
              </p>
            ) : (
              <PluginList plugins={plugins} />
            )}
          </div>
        </section>
      </main>
    </>
  );
}
