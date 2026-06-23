import type { FieldSchema } from '@arqel-dev/types/fields';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { DateTimeInput } from '../src/date/index.js';
import { FileInput, ImageInput } from '../src/file/index.js';
import { NumberInput } from '../src/number/index.js';
import { BelongsToInput } from '../src/relationship/index.js';
import { MultiSelectInput } from '../src/select/index.js';
import { PasswordInput } from '../src/text/index.js';
import { setMockTranslations } from './setup.js';

const baseField = {
  required: false,
  readonly: false,
  disabled: false,
  placeholder: null,
  helperText: null,
  defaultValue: null,
  columnSpan: 1,
  live: false,
  liveDebounce: null,
  validation: { rules: [], messages: {}, attribute: null },
  visibility: { create: true, edit: true, detail: true, table: true, canSee: true },
  dependsOn: [],
};

const number: FieldSchema = {
  ...baseField,
  type: 'number',
  name: 'qty',
  label: 'Quantity',
  component: 'NumberInput',
  props: {},
};

const file: FieldSchema = {
  ...baseField,
  type: 'file',
  name: 'avatar',
  label: 'Avatar',
  component: 'FileInput',
  props: { disk: 'public' },
};

const image: FieldSchema = {
  ...baseField,
  type: 'image',
  name: 'cover',
  label: 'Cover',
  component: 'ImageInput',
  props: { disk: 'public' },
};

const password: FieldSchema = {
  ...baseField,
  type: 'password',
  name: 'secret',
  label: 'Secret',
  component: 'PasswordInput',
  props: { autocomplete: 'new-password' },
};

const multi: FieldSchema = {
  ...baseField,
  type: 'multiSelect',
  name: 'tags',
  label: 'Tags',
  component: 'MultiSelectInput',
  props: {
    multiple: true,
    options: [{ value: 'a', label: 'Alpha' }],
  },
};

const belongsTo: FieldSchema = {
  ...baseField,
  type: 'belongsTo',
  name: 'author',
  label: 'Author',
  component: 'BelongsToInput',
  props: {
    relatedResource: 'users',
    relationship: 'author',
    searchable: true,
    searchColumns: ['name'],
    preload: false,
    searchRoute: '/search',
  },
};

const dateTime: FieldSchema = {
  ...baseField,
  type: 'dateTime',
  name: 'starts_at',
  label: 'Starts at',
  component: 'DateTimeInput',
  props: {
    format: 'yyyy-MM-dd HH:mm',
    displayFormat: 'dd/MM/yyyy HH:mm',
    timezone: 'America/Sao_Paulo',
  },
};

describe('NumberInput stepper a11y (i18n)', () => {
  it('uses the English fallback aria-labels with no dictionary', () => {
    render(<NumberInput field={number} value={1} onChange={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Increment' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Decrement' })).toBeInTheDocument();
  });

  it('localizes stepper aria-labels via arqel.fields keys', () => {
    setMockTranslations({
      arqel: { fields: { increment: 'Incrementar', decrement: 'Decrementar' } },
    });
    render(<NumberInput field={number} value={1} onChange={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Incrementar' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Decrementar' })).toBeInTheDocument();
  });
});

describe('FileInput a11y (i18n)', () => {
  it('falls back to the English accessible name', () => {
    render(<FileInput field={file} value={null} onChange={vi.fn()} />);
    expect(screen.getByRole('region', { name: 'File upload' })).toBeInTheDocument();
  });

  it('localizes the upload region aria-label', () => {
    setMockTranslations({ arqel: { fields: { file: { upload: 'Envio de arquivo' } } } });
    render(<FileInput field={file} value={null} onChange={vi.fn()} />);
    expect(screen.getByRole('region', { name: 'Envio de arquivo' })).toBeInTheDocument();
  });
});

describe('FileInput visible browse label (i18n)', () => {
  it('falls back to the English visible labels', () => {
    const { rerender } = render(<FileInput field={file} value={null} onChange={vi.fn()} />);
    expect(screen.getByText('Browse')).toBeInTheDocument();
    rerender(<FileInput field={file} value="avatar.png" onChange={vi.fn()} />);
    expect(screen.getByText('Choose another file')).toBeInTheDocument();
  });

  it('localizes the visible browse / replace labels', () => {
    setMockTranslations({
      arqel: { fields: { file: { browse: 'Procurar', choose_another: 'Escolher outro arquivo' } } },
    });
    const { rerender } = render(<FileInput field={file} value={null} onChange={vi.fn()} />);
    expect(screen.getByText('Procurar')).toBeInTheDocument();
    rerender(<FileInput field={file} value="avatar.png" onChange={vi.fn()} />);
    expect(screen.getByText('Escolher outro arquivo')).toBeInTheDocument();
  });

  it('falls back to the English drop-zone instruction', () => {
    render(<FileInput field={file} value={null} onChange={vi.fn()} />);
    expect(screen.getByText('Drag a file here or click to browse')).toBeInTheDocument();
  });

  it('localizes the drop-zone instruction', () => {
    setMockTranslations({
      arqel: {
        fields: { file: { drop_hint: 'Arraste um arquivo aqui ou clique para procurar' } },
      },
    });
    render(<FileInput field={file} value={null} onChange={vi.fn()} />);
    expect(screen.getByText('Arraste um arquivo aqui ou clique para procurar')).toBeInTheDocument();
  });
});

describe('ImageInput alt + visible label (i18n)', () => {
  it('falls back to English alt + choose label', () => {
    render(<ImageInput field={image} value="https://example.com/a.png" onChange={vi.fn()} />);
    expect(screen.getByAltText('Preview')).toBeInTheDocument();
    expect(screen.getByText('Replace image')).toBeInTheDocument();
  });

  it('shows the choose label when there is no preview', () => {
    render(<ImageInput field={image} value={null} onChange={vi.fn()} />);
    expect(screen.getByText('Choose image')).toBeInTheDocument();
  });

  it('localizes the preview alt + replace label', () => {
    setMockTranslations({
      arqel: {
        fields: {
          image: {
            preview_alt: 'Pré-visualização',
            replace: 'Substituir imagem',
            choose: 'Escolher imagem',
          },
        },
      },
    });
    render(<ImageInput field={image} value="https://example.com/a.png" onChange={vi.fn()} />);
    expect(screen.getByAltText('Pré-visualização')).toBeInTheDocument();
    expect(screen.getByText('Substituir imagem')).toBeInTheDocument();
  });
});

describe('PasswordInput reveal toggle a11y (i18n)', () => {
  it('falls back to the English accessible name', () => {
    render(<PasswordInput field={password} value="hunter2" onChange={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Show password' })).toBeInTheDocument();
  });

  it('localizes the show/hide accessible name and swaps on toggle', () => {
    setMockTranslations({
      arqel: { fields: { password: { show: 'Mostrar senha', hide: 'Ocultar senha' } } },
    });
    render(<PasswordInput field={password} value="hunter2" onChange={vi.fn()} />);
    const button = screen.getByRole('button', { name: 'Mostrar senha' });
    fireEvent.click(button);
    expect(screen.getByRole('button', { name: 'Ocultar senha' })).toBeInTheDocument();
  });
});

describe('MultiSelectInput chip remove a11y (i18n)', () => {
  it('falls back to "Remove :label" in English', () => {
    render(<MultiSelectInput field={multi} value={['a']} onChange={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Remove Alpha' })).toBeInTheDocument();
  });

  it('localizes the remove aria-label with the interpolated label', () => {
    setMockTranslations({ arqel: { fields: { multiselect: { remove: 'Remover :label' } } } });
    render(<MultiSelectInput field={multi} value={['a']} onChange={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Remover Alpha' })).toBeInTheDocument();
  });
});

describe('BelongsToInput search placeholder (i18n)', () => {
  it('falls back to the English template with the resource interpolated', () => {
    const { container } = render(
      <BelongsToInput field={belongsTo} value={null} onChange={vi.fn()} />,
    );
    const input = container.querySelector('input[type="text"]') as HTMLInputElement;
    expect(input.placeholder).toBe('Search users…');
  });

  it('localizes the search placeholder', () => {
    setMockTranslations({ arqel: { fields: { belongsto: { search: 'Buscar :resource…' } } } });
    const { container } = render(
      <BelongsToInput field={belongsTo} value={null} onChange={vi.fn()} />,
    );
    const input = container.querySelector('input[type="text"]') as HTMLInputElement;
    expect(input.placeholder).toBe('Buscar users…');
  });

  it('still prefers an explicit field placeholder over the template', () => {
    const explicit: FieldSchema = { ...belongsTo, placeholder: 'Pick one' };
    const { container } = render(
      <BelongsToInput field={explicit} value={null} onChange={vi.fn()} />,
    );
    const input = container.querySelector('input[type="text"]') as HTMLInputElement;
    expect(input.placeholder).toBe('Pick one');
  });
});

describe('DateTimeInput timezone (round-3 sibling gap)', () => {
  it('renders an absolute UTC instant in the declared timezone', () => {
    // 2024-01-02T12:00Z is 09:00 in America/Sao_Paulo (UTC-3, no DST).
    const { container } = render(
      <DateTimeInput field={dateTime} value="2024-01-02T12:00:00Z" onChange={vi.fn()} />,
    );
    const input = container.querySelector('input[type="datetime-local"]') as HTMLInputElement;
    expect(input.value).toBe('2024-01-02T09:00');
  });

  it('re-anchors an edited wall-clock back to a UTC instant', () => {
    const onChange = vi.fn();
    const { container } = render(
      <DateTimeInput field={dateTime} value="2024-01-02T12:00:00Z" onChange={onChange} />,
    );
    const input = container.querySelector('input[type="datetime-local"]') as HTMLInputElement;
    // Simulate the user picking 09:30 local (Sao Paulo) → 12:30 UTC.
    fireEvent.change(input, { target: { value: '2024-01-02T09:30' } });
    expect(onChange).toHaveBeenCalled();
    const sent = onChange.mock.calls[0]?.[0] as string;
    expect(new Date(sent).toISOString()).toBe('2024-01-02T12:30:00.000Z');
  });

  it('passes through a zoneless wall-clock value unchanged', () => {
    const onChange = vi.fn();
    const noTz: FieldSchema = {
      ...dateTime,
      props: { format: 'yyyy-MM-dd HH:mm', displayFormat: 'yyyy-MM-dd HH:mm' },
    };
    const { container } = render(
      <DateTimeInput field={noTz} value="2024-01-02T09:30" onChange={onChange} />,
    );
    const input = container.querySelector('input[type="datetime-local"]') as HTMLInputElement;
    expect(input.value).toBe('2024-01-02T09:30');
  });
});
