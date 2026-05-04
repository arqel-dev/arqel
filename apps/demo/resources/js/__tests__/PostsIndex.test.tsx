import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import PostsIndex from '../Pages/Admin/Posts/Index';

describe('<PostsIndex />', () => {
  it('renders posts table with rows', () => {
    render(
      <PostsIndex
        fields={[{ name: 'title', type: 'text' }]}
        posts={[
          { id: 1, title: 'Hello', slug: 'hello', state: 'draft', published_at: null },
          { id: 2, title: 'World', slug: 'world', state: 'published', published_at: '2026-01-01' },
        ]}
      />,
    );
    expect(screen.getByTestId('posts-index')).toBeInTheDocument();
    expect(screen.getByTestId('post-row-1')).toHaveTextContent('Hello');
    expect(screen.getByTestId('post-row-2')).toHaveTextContent('published');
  });
});
