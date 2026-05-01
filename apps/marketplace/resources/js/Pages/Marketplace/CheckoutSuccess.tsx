import { Head } from '@inertiajs/react';
import { useState } from 'react';
import type { Plugin } from '../../types';

type Props = {
  plugin: Plugin;
  license_key: string;
  download_url: string;
};

export default function CheckoutSuccess({ plugin, license_key, download_url }: Props): JSX.Element {
  const [copied, setCopied] = useState(false);

  const onCopy = async (): Promise<void> => {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
      await navigator.clipboard.writeText(license_key);
    }
    setCopied(true);
  };

  return (
    <>
      <Head title={`Compra confirmada — ${plugin.name}`} />
      <main className="mx-auto max-w-2xl px-4 py-12 text-center">
        <div data-testid="celebration" className="mb-4 text-5xl">
          ✅
        </div>
        <h1 className="text-2xl font-bold">Compra confirmada!</h1>
        <p className="mt-2 text-neutral-600">
          Obrigado por adquirir <strong>{plugin.name}</strong>. Sua licença foi gerada com sucesso.
        </p>

        <section className="mt-8 rounded-lg border border-neutral-200 bg-neutral-50 p-6 text-left">
          <h2 className="text-sm font-semibold text-neutral-700">Sua license key</h2>
          <div className="mt-2 flex items-center gap-2">
            <code
              data-testid="license-key"
              className="flex-1 break-all rounded bg-neutral-900 px-3 py-2 font-mono text-sm text-green-300"
            >
              {license_key}
            </code>
            <button
              type="button"
              onClick={onCopy}
              data-testid="copy-license"
              className="rounded border border-neutral-300 bg-white px-3 py-2 text-sm font-medium hover:border-blue-400"
            >
              {copied ? 'Copiado!' : 'Copiar'}
            </button>
          </div>
          <p className="mt-3 text-xs text-neutral-500">
            Guarde essa chave — você vai precisar dela para ativar o plugin.
          </p>
        </section>

        <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
          <a
            href={download_url}
            data-testid="download-link"
            className="rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700"
          >
            Baixar plugin
          </a>
          <a
            href={`/plugins/${plugin.slug}`}
            data-testid="installation-link"
            className="rounded-lg border border-neutral-300 px-4 py-3 font-medium hover:border-blue-400"
          >
            Ver instruções de instalação
          </a>
        </div>
      </main>
    </>
  );
}
