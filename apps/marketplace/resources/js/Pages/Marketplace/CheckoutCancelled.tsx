import { Head } from '@inertiajs/react';
import type { Plugin } from '../../types';

type Props = {
  plugin: Plugin;
};

export default function CheckoutCancelled({ plugin }: Props): JSX.Element {
  return (
    <>
      <Head title={`Compra cancelada — ${plugin.name}`} />
      <main className="mx-auto max-w-2xl px-4 py-12 text-center">
        <h1 className="text-2xl font-bold">Compra cancelada</h1>
        <p className="mt-2 text-neutral-600">
          Tudo bem — nada foi cobrado. Você pode tentar novamente quando quiser.
        </p>

        <div className="mt-8 flex flex-col gap-2 sm:flex-row sm:justify-center">
          <a
            href={`/checkout/${plugin.slug}`}
            data-testid="retry-link"
            className="rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700"
          >
            Tentar novamente
          </a>
          <a
            href={`/plugins/${plugin.slug}`}
            data-testid="back-link"
            className="rounded-lg border border-neutral-300 px-4 py-3 font-medium hover:border-blue-400"
          >
            Voltar para o plugin
          </a>
        </div>
      </main>
    </>
  );
}
