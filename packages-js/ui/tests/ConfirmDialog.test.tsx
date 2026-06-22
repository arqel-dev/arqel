import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ConfirmDialog } from '../src/action/ConfirmDialog.js';

const { pageMock } = vi.hoisted(() => ({ pageMock: vi.fn(() => ({ props: {} })) }));
vi.mock('@inertiajs/react', () => ({ usePage: pageMock }));

afterEach(() => {
  pageMock.mockReset();
  pageMock.mockReturnValue({ props: {} });
});

describe('ConfirmDialog i18n', () => {
  it('falls back to English chrome without an i18n prop', () => {
    render(<ConfirmDialog open onOpenChange={() => {}} onConfirm={() => {}} />);
    expect(screen.getByText('Are you sure?')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Delete' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeInTheDocument();
  });

  it('translates heading/submit/cancel from props.i18n', () => {
    pageMock.mockReturnValue({
      props: {
        i18n: {
          locale: 'pt_BR',
          available: ['pt_BR'],
          translations: {
            arqel: {
              actions: { delete: 'Excluir', cancel: 'Cancelar' },
              messages: { delete_confirm: 'Tem certeza?' },
            },
          },
        },
      },
    });
    render(<ConfirmDialog open onOpenChange={() => {}} onConfirm={() => {}} />);
    expect(screen.getByText('Tem certeza?')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Excluir' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Cancelar' })).toBeInTheDocument();
  });

  it('keeps an explicit config heading/label over the translation', () => {
    pageMock.mockReturnValue({
      props: {
        i18n: {
          locale: 'pt_BR',
          available: ['pt_BR'],
          translations: { arqel: { actions: { delete: 'Excluir' } } },
        },
      },
    });
    render(
      <ConfirmDialog
        open
        onOpenChange={() => {}}
        onConfirm={() => {}}
        config={{ heading: 'Delete post?', submitLabel: 'Remove' }}
      />,
    );
    expect(screen.getByText('Delete post?')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Remove' })).toBeInTheDocument();
  });
});
