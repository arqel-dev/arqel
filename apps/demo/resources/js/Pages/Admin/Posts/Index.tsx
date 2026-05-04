import type { FieldDefinition, PostListItem } from '../../../types';

interface Props {
  fields: FieldDefinition[];
  posts: PostListItem[];
}

export default function PostsIndex({ fields, posts }: Props) {
  return (
    <main data-testid="posts-index" className="p-8">
      <h1 className="text-2xl font-bold">Posts</h1>
      <p className="text-sm text-gray-500">
        {fields.length} fields configurados • {posts.length} registros
      </p>
      <table className="mt-4 w-full border">
        <thead>
          <tr>
            <th className="border px-2 py-1 text-left">Title</th>
            <th className="border px-2 py-1 text-left">State</th>
          </tr>
        </thead>
        <tbody>
          {posts.map((post) => (
            <tr key={post.id} data-testid={`post-row-${post.id}`}>
              <td className="border px-2 py-1">{post.title}</td>
              <td className="border px-2 py-1">{post.state}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </main>
  );
}
