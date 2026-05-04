import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import TagsIndex from '../Pages/Admin/Tags/Index';

describe('<TagsIndex />', () => {
  it('lists tags with optional category', () => {
    render(
      <TagsIndex
        fields={[{ name: 'name', type: 'text' }]}
        tags={[
          { id: 1, name: 'react', slug: 'react', category: 'frontend' },
          { id: 2, name: 'php', slug: 'php', category: null },
        ]}
      />,
    );
    expect(screen.getByTestId('tags-index')).toBeInTheDocument();
    expect(screen.getByTestId('tag-1')).toHaveTextContent('frontend');
    expect(screen.getByTestId('tag-2')).toHaveTextContent('php');
  });
});
