import type {
  BadgeColumnSchema,
  BooleanColumnSchema,
  DateColumnSchema,
  IconColumnSchema,
  ImageColumnSchema,
  NumberColumnSchema,
  RelationshipColumnSchema,
  TextColumnSchema,
} from '@arqel/types/tables';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { TableCell } from '../src/table/cells.js';

const baseColumn = {
  name: 'value',
  label: 'Value',
  sortable: false,
  searchable: false,
  copyable: false,
  hidden: false,
  hiddenOnMobile: false,
  align: 'start' as const,
  width: null,
  tooltip: null,
};

describe('TableCell', () => {
  it('truncates text with ellipsis', () => {
    const column: TextColumnSchema = {
      ...baseColumn,
      type: 'text',
      props: { truncate: 4, weight: 'bold' },
    };
    render(<TableCell column={column} value="abcdefgh" />);
    expect(screen.getByText('abcd…')).toHaveClass('font-bold');
  });

  it('renders badge with matching option label', () => {
    const column: BadgeColumnSchema = {
      ...baseColumn,
      type: 'badge',
      props: { options: [{ value: 'a', label: 'Active' }], pill: true },
    };
    render(<TableCell column={column} value="a" />);
    expect(screen.getByText('Active')).toBeInTheDocument();
  });

  it('boolean cell announces true/false via aria-label', () => {
    const column: BooleanColumnSchema = {
      ...baseColumn,
      type: 'boolean',
      props: { trueIcon: 'YES', falseIcon: 'NO' },
    };
    const { rerender } = render(<TableCell column={column} value={true} />);
    expect(screen.getByLabelText('true')).toHaveTextContent('YES');
    rerender(<TableCell column={column} value={false} />);
    expect(screen.getByLabelText('false')).toHaveTextContent('NO');
  });

  it('date cell formats ISO via mode', () => {
    const column: DateColumnSchema = {
      ...baseColumn,
      type: 'date',
      props: { mode: 'date', format: 'yyyy-MM-dd' },
    };
    render(<TableCell column={column} value="2026-01-15T00:00:00Z" />);
    expect(screen.getByRole('time')).toHaveAttribute('datetime');
  });

  it('number cell formats with thousands separator + suffix', () => {
    const column: NumberColumnSchema = {
      ...baseColumn,
      type: 'number',
      props: { decimals: 2, thousandsSeparator: '.', decimalSeparator: ',', suffix: ' kg' },
    };
    render(<TableCell column={column} value={1234.5} />);
    expect(screen.getByText('1.234,50 kg')).toBeInTheDocument();
  });

  it('icon cell exposes label', () => {
    const column: IconColumnSchema = {
      ...baseColumn,
      type: 'icon',
      props: { icon: '★', size: 'md' },
    };
    render(<TableCell column={column} value={null} />);
    expect(screen.getByLabelText('★')).toBeInTheDocument();
  });

  it('image cell honours circular shape', () => {
    const column: ImageColumnSchema = {
      ...baseColumn,
      type: 'image',
      props: { shape: 'circular', size: 24 },
    };
    const { container } = render(<TableCell column={column} value="/avatar.png" />);
    expect(container.querySelector('img')).toHaveClass('rounded-full');
  });

  it('relationship cell drills into attribute', () => {
    const column: RelationshipColumnSchema = {
      ...baseColumn,
      type: 'relationship',
      props: { relationship: 'team', attribute: 'name' },
    };
    render(<TableCell column={column} value={{ id: 1, name: 'Engineering' }} />);
    expect(screen.getByText('Engineering')).toBeInTheDocument();
  });

  it('renders em-dash for null date', () => {
    const column: DateColumnSchema = {
      ...baseColumn,
      type: 'date',
      props: { mode: 'datetime', format: '' },
    };
    render(<TableCell column={column} value={null} />);
    expect(screen.getByText('—')).toBeInTheDocument();
  });
});
