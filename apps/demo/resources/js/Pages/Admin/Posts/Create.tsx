import type { FieldDefinition } from '../../../types';

interface Props {
  fields: FieldDefinition[];
}

export default function PostsCreate({ fields }: Props) {
  return (
    <main data-testid="posts-create" className="p-8">
      <h1 className="text-2xl font-bold">Novo post</h1>
      <form className="mt-4 space-y-4">
        {fields.map((field) => (
          <div key={field.name} data-testid={`field-${field.name}`}>
            <label className="block text-sm font-medium" htmlFor={`f-${field.name}`}>
              {field.name}
            </label>
            <input id={`f-${field.name}`} name={field.name} className="w-full border" />
            <span className="text-xs text-gray-500">type: {field.type}</span>
          </div>
        ))}
      </form>
    </main>
  );
}
