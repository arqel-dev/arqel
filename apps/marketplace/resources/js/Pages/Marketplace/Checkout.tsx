import { Head } from '@inertiajs/react';
import type { Plugin } from '../../types';

type Summary = {
  price_cents: number;
  currency: string;
  fee_estimate_cents: number;
  total_cents: number;
};

type Props = {
  plugin: Plugin;
  summary: Summary;
};

function formatMoney(cents: number, currency: string): string {
  const value = (cents / 100).toFixed(2);
  return `${currency} ${value}`;
}

export default function Checkout({ plugin, summary }: Props): JSX.Element {
  return (
    <>
      <Head title={`Checkout — ${plugin.name}`} />
      <main className="mx-auto max-w-2xl px-4 py-12">
        <h1 className="text-2xl font-bold">Finalizar compra</h1>
        <p className="mt-2 text-neutral-600">
          Você está prestes a comprar uma licença para <strong>{plugin.name}</strong>.
        </p>

        <section
          data-testid="summary-card"
          className="mt-6 rounded-lg border border-neutral-200 bg-white p-6"
        >
          <h2 className="text-lg font-semibold">{plugin.name}</h2>
          <p className="mt-1 text-sm text-neutral-600">{plugin.description}</p>

          <dl className="mt-4 space-y-2 text-sm">
            <div className="flex justify-between">
              <dt className="text-neutral-600">Preço</dt>
              <dd data-testid="summary-price">
                {formatMoney(summary.price_cents, summary.currency)}
              </dd>
            </div>
            <div className="flex justify-between text-neutral-500">
              <dt>Taxa estimada da plataforma</dt>
              <dd data-testid="summary-fee">
                {formatMoney(summary.fee_estimate_cents, summary.currency)}
              </dd>
            </div>
            <div className="flex justify-between border-t border-neutral-200 pt-2 font-semibold">
              <dt>Total</dt>
              <dd data-testid="summary-total">
                {formatMoney(summary.total_cents, summary.currency)}
              </dd>
            </div>
          </dl>
        </section>

        <form method="POST" action={`/checkout/${plugin.slug}/initiate`} className="mt-6">
          <input
            type="hidden"
            name="_token"
            value={
              typeof document !== 'undefined'
                ? (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '')
                : ''
            }
          />
          <button
            type="submit"
            data-testid="proceed-payment"
            className="w-full rounded-lg bg-blue-600 px-4 py-3 text-center font-semibold text-white hover:bg-blue-700"
          >
            Continuar para pagamento
          </button>
        </form>

        <a
          href={`/plugins/${plugin.slug}`}
          data-testid="cancel-link"
          className="mt-4 block text-center text-sm text-neutral-600 hover:text-neutral-900"
        >
          Cancelar
        </a>
      </main>
    </>
  );
}
