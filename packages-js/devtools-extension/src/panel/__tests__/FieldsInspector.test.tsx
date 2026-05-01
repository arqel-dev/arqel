import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { type FieldSchema, FieldsInspector } from '../FieldsInspector';

function makeFields(): FieldSchema[] {
  return [
    {
      name: 'email',
      type: 'text',
      label: 'Email',
      required: true,
      visible: true,
      rules: ['required', 'email'],
      meta: { dependsOn: 'role', visibleWhen: { role: 'admin' } },
    },
    {
      name: 'role',
      type: 'select',
      required: false,
      visible: true,
      rules: [],
    },
    {
      name: 'notes',
      type: 'text',
      required: false,
      visible: false,
    },
  ];
}

describe('<FieldsInspector />', () => {
  it('renders one row per field with type badge and visible/total counter', () => {
    render(<FieldsInspector fields={makeFields()} />);
    expect(screen.getAllByTestId('field-row')).toHaveLength(3);
    expect(screen.getByTestId('fields-counter')).toHaveTextContent('2 visible / 3 total');
    const badges = screen.getAllByTestId('field-type-badge');
    expect(badges.map((b) => b.textContent)).toEqual(['text', 'select', 'text']);
  });

  it('filters by type via the type selector', () => {
    render(<FieldsInspector fields={makeFields()} />);
    fireEvent.change(screen.getByTestId('fields-type-filter'), { target: { value: 'select' } });
    const rows = screen.getAllByTestId('field-row');
    expect(rows).toHaveLength(1);
    expect(rows[0]).toHaveAttribute('data-name', 'role');
  });

  it('filters by name search (case-insensitive)', () => {
    render(<FieldsInspector fields={makeFields()} />);
    fireEvent.change(screen.getByTestId('fields-search'), { target: { value: 'NOT' } });
    const rows = screen.getAllByTestId('field-row');
    expect(rows).toHaveLength(1);
    expect(rows[0]).toHaveAttribute('data-name', 'notes');
  });

  it('expands a detailed view with rules, depends-on, visibility rule and meta on click', () => {
    render(<FieldsInspector fields={makeFields()} />);
    expect(screen.queryByTestId('field-detail')).not.toBeInTheDocument();

    const toggles = screen.getAllByTestId('field-toggle');
    fireEvent.click(toggles[0] as HTMLElement);

    expect(screen.getByTestId('field-detail')).toBeInTheDocument();
    const ruleItems = screen.getByTestId('field-rules').querySelectorAll('li');
    expect(ruleItems).toHaveLength(2);
    expect(screen.getByTestId('field-depends-on')).toHaveTextContent('role');
    expect(screen.getByTestId('field-visible-when').textContent).toContain('"role": "admin"');
    expect(screen.getByTestId('field-meta')).toBeInTheDocument();

    fireEvent.click(toggles[0] as HTMLElement);
    expect(screen.queryByTestId('field-detail')).not.toBeInTheDocument();
  });

  it('shows empty state when there are no fields', () => {
    render(<FieldsInspector fields={[]} />);
    expect(screen.getByTestId('fields-empty')).toHaveTextContent(
      'No fields detected in the current pageProps.',
    );
    expect(screen.queryByTestId('fields-list')).not.toBeInTheDocument();
  });
});
