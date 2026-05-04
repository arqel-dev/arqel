import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import Dashboard from '../Pages/Admin/Dashboard';

describe('<Dashboard />', () => {
  it('renders panel id and stats', () => {
    render(
      <Dashboard
        panel={{ id: 'admin', resources: ['Post', 'Tag', 'Category'] }}
        stats={{ posts: 10, published: 4, draft: 6 }}
      />,
    );
    expect(screen.getByTestId('admin-dashboard')).toBeInTheDocument();
    expect(screen.getByTestId('stat-posts')).toHaveTextContent('10');
    expect(screen.getByTestId('stat-published')).toHaveTextContent('4');
  });
});
