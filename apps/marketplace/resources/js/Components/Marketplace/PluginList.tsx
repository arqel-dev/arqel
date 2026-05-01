import type { Plugin } from '../../types';
import { PluginCard } from './PluginCard';

type Props = {
  plugins: Plugin[];
  emptyMessage?: string;
};

export function PluginList({
  plugins,
  emptyMessage = 'Nenhum plugin encontrado.',
}: Props): JSX.Element {
  if (plugins.length === 0) {
    return <p className="text-sm text-neutral-500">{emptyMessage}</p>;
  }

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {plugins.map((plugin) => (
        <PluginCard key={plugin.id} plugin={plugin} />
      ))}
    </div>
  );
}
