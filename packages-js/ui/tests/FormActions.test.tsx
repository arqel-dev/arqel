import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { FormActions } from '../src/form/FormActions.js';

const { pageMock } = vi.hoisted(() => ({ pageMock: vi.fn(() => ({ props: {} })) }));
vi.mock('@inertiajs/react', () => ({ usePage: pageMock }));

afterEach(() => {
  pageMock.mockReset();
  pageMock.mockReturnValue({ props: {} });
});

describe('FormActions', () => {
  it('renders submit button with default label', () => {
    render(<FormActions />);
    expect(screen.getByRole('button', { name: 'Save' })).toHaveAttribute('type', 'submit');
  });

  it('translates default Save/Cancel/Saving from props.i18n', () => {
    pageMock.mockReturnValue({
      props: {
        i18n: {
          locale: 'pt_BR',
          available: ['pt_BR'],
          translations: { form: { save: 'Salvar', cancel: 'Cancelar', saving: 'Salvando…' } },
        },
      },
    });
    const { rerender } = render(<FormActions onCancel={() => {}} />);
    expect(screen.getByRole('button', { name: 'Salvar' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Cancelar' })).toBeInTheDocument();
    rerender(<FormActions processing />);
    expect(screen.getByRole('button', { name: 'Salvando…' })).toBeInTheDocument();
  });

  it('keeps an explicit label over the translation', () => {
    pageMock.mockReturnValue({
      props: {
        i18n: { locale: 'pt_BR', available: ['pt_BR'], translations: { form: { save: 'Salvar' } } },
      },
    });
    render(<FormActions submitLabel="Publish" />);
    expect(screen.getByRole('button', { name: 'Publish' })).toBeInTheDocument();
  });

  it('shows processing label and disables submit when processing', () => {
    render(<FormActions processing />);
    const submit = screen.getByRole('button', { name: 'Saving…' });
    expect(submit).toBeDisabled();
  });

  it('renders cancel and fires onCancel', async () => {
    const user = userEvent.setup();
    const onCancel = vi.fn();
    render(<FormActions onCancel={onCancel} />);
    await user.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(onCancel).toHaveBeenCalled();
  });
});
