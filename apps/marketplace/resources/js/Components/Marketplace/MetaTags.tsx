import { Head } from '@inertiajs/react';

/**
 * Schema.org JSON-LD payload (validado em runtime apenas pela serialização).
 * Mantemos `unknown` para permitir Product/Offer/BreadcrumbList sem amarrar o tipo.
 */
export type JsonLd = Record<string, unknown>;

export type MetaTagsProps = {
  title: string;
  description: string;
  ogImage?: string | null;
  ogType?: 'website' | 'article' | 'product' | 'profile';
  twitterCard?: 'summary' | 'summary_large_image';
  canonical?: string;
  jsonLd?: JsonLd;
};

const DEFAULT_OG_IMAGE = '/images/og/marketplace-default.png';

/**
 * Serializa JSON-LD com escape de `<` para prevenir injecao de </script>.
 * O conteudo passa por JSON.stringify (escapando aspas) e depois substituimos
 * `<` por `<` para defesa em profundidade.
 */
function serializeJsonLd(payload: JsonLd): string {
  return JSON.stringify(payload).replace(/</g, '\\u003c');
}

/**
 * Injeta meta tags SEO + Open Graph + Twitter Card + JSON-LD no <head>.
 *
 * Usa o `<Head>` do Inertia, que substitui tags por chave (`head-key`).
 * Defaults inteligentes: og:image fallback, twitter:card 'summary_large_image',
 * og:type 'website'.
 */
export function MetaTags({
  title,
  description,
  ogImage,
  ogType = 'website',
  twitterCard = 'summary_large_image',
  canonical,
  jsonLd,
}: MetaTagsProps): JSX.Element {
  const image = ogImage ?? DEFAULT_OG_IMAGE;

  return (
    <Head title={title}>
      <meta name="description" content={description} head-key="description" />
      <meta property="og:title" content={title} head-key="og:title" />
      <meta property="og:description" content={description} head-key="og:description" />
      <meta property="og:type" content={ogType} head-key="og:type" />
      <meta property="og:image" content={image} head-key="og:image" />
      <meta name="twitter:card" content={twitterCard} head-key="twitter:card" />
      <meta name="twitter:title" content={title} head-key="twitter:title" />
      <meta name="twitter:description" content={description} head-key="twitter:description" />
      <meta name="twitter:image" content={image} head-key="twitter:image" />
      {canonical !== undefined && <link rel="canonical" href={canonical} head-key="canonical" />}
      {jsonLd !== undefined && (
        <script
          type="application/ld+json"
          // biome-ignore lint/security/noDangerouslySetInnerHtml: JSON-LD requires raw script content; serializeJsonLd escapes `<` to <
          dangerouslySetInnerHTML={{ __html: serializeJsonLd(jsonLd) }}
          head-key="json-ld"
        />
      )}
    </Head>
  );
}
