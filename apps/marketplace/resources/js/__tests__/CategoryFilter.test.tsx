import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { CategoryFilter } from '../Components/Marketplace/CategoryFilter';
import type { PluginCategory } from '../types';

const categories: PluginCategory[] = [
  { id: 1, slug: 'fields', name: 'Fields' },
  { id: 2, slug: 'widgets', name: 'Widgets' },
];

describe('<CategoryFilter />', () => {
  it('renders all categories plus the "Todas" option', () => {
    render(<CategoryFilter categories={categories} onSelect={() => undefined} />);
    expect(screen.getByText('Todas')).toBeInTheDocument();
    expect(screen.getByText('Fields')).toBeInTheDocument();
    expect(screen.getByText('Widgets')).toBeInTheDocument();
  });

  it('invokes onSelect with the category slug when clicked', () => {
    const onSelect = vi.fn();
    render(<CategoryFilter categories={categories} onSelect={onSelect} />);
    fireEvent.click(screen.getByTestId('category-widgets'));
    expect(onSelect).toHaveBeenCalledWith('widgets');
  });
});
