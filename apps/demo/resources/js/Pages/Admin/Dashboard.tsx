interface Stats {
  posts: number;
  published: number;
  draft: number;
}

interface Props {
  panel: { id: string | null; resources: string[] };
  stats: Stats;
}

export default function Dashboard({ panel, stats }: Props) {
  return (
    <main data-testid="admin-dashboard" className="p-8">
      <h1 className="text-2xl font-bold">Arqel Demo — Painel {panel.id ?? '?'}</h1>
      <p className="mt-2 text-sm text-gray-500">Recursos registrados: {panel.resources.length}</p>
      <ul className="mt-4 grid grid-cols-3 gap-4">
        <li data-testid="stat-posts" className="rounded border p-4">
          <span className="text-xs uppercase">Posts</span>
          <strong className="block text-2xl">{stats.posts}</strong>
        </li>
        <li data-testid="stat-published" className="rounded border p-4">
          <span className="text-xs uppercase">Published</span>
          <strong className="block text-2xl">{stats.published}</strong>
        </li>
        <li data-testid="stat-draft" className="rounded border p-4">
          <span className="text-xs uppercase">Draft</span>
          <strong className="block text-2xl">{stats.draft}</strong>
        </li>
      </ul>
    </main>
  );
}
