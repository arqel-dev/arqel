import type { PluginCategory } from '../../types';

type Props = {
  categories: PluginCategory[];
  selected?: string | null;
  onSelect: (slug: string | null) => void;
};

export function CategoryFilter({ categories, selected = null, onSelect }: Props): JSX.Element {
  return (
    <nav aria-label="Categorias" className="space-y-1">
      <button
        type="button"
        onClick={() => onSelect(null)}
        className={`block w-full rounded px-3 py-2 text-left text-sm ${
          selected === null
            ? 'bg-blue-100 font-medium text-blue-900'
            : 'text-neutral-700 hover:bg-neutral-100'
        }`}
      >
        Todas
      </button>
      {categories.map((category) => (
        <button
          key={category.id}
          type="button"
          data-testid={`category-${category.slug}`}
          onClick={() => onSelect(category.slug)}
          className={`block w-full rounded px-3 py-2 text-left text-sm ${
            selected === category.slug
              ? 'bg-blue-100 font-medium text-blue-900'
              : 'text-neutral-700 hover:bg-neutral-100'
          }`}
        >
          {category.name}
        </button>
      ))}
    </nav>
  );
}
