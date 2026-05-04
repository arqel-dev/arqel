import type { FieldDefinition, TagListItem } from '../../../types';

interface Props {
  fields: FieldDefinition[];
  tags: TagListItem[];
}

export default function TagsIndex({ fields, tags }: Props) {
  return (
    <main data-testid="tags-index" className="p-8">
      <h1 className="text-2xl font-bold">Tags</h1>
      <p className="text-sm text-gray-500">
        {fields.length} fields • {tags.length} tags
      </p>
      <ul className="mt-4 space-y-1">
        {tags.map((tag) => (
          <li key={tag.id} data-testid={`tag-${tag.id}`}>
            <strong>{tag.name}</strong>
            {tag.category && <span className="ml-2 text-xs">({tag.category})</span>}
          </li>
        ))}
      </ul>
    </main>
  );
}
