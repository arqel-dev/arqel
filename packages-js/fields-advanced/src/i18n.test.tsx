/**
 * Cross-component i18n coverage for the advanced field editors.
 *
 * Round-7 sweep: every user-facing string in the advanced editors
 * (aria-labels, visible button text, placeholders, empty states, sr-only
 * status) must route through `useArqelTranslations()` so a non-English panel
 * renders localized chrome — and must fall back to the original English
 * literal when the key is absent (keeping accessible names stable).
 *
 * The hook reads Inertia's `usePage().props.i18n`. We stub it per-suite: an
 * empty page exercises the English fallback path; a pt_BR dictionary exercises
 * the localized path. The dictionary is nested (dot-path) to match the shape
 * `HandleArqelInertiaRequests` emits.
 */

import type { FieldSchema } from '@arqel-dev/types/fields';
import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { CodeInput } from './code/CodeInput.js';
import { KeyValueInput } from './key-value/KeyValueInput.js';
import { TagsInput } from './tags/TagsInput.js';
import { WizardInput } from './wizard/WizardInput.js';

const usePageMock = vi.fn(() => ({ props: {} as Record<string, unknown> }));

vi.mock('@inertiajs/react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/react')>();
  return { ...actual, usePage: () => usePageMock() };
});

const ptBr = {
  locale: 'pt_BR',
  translations: {
    arqel: {
      fields_advanced: {
        tags_remove: 'Remover tag :tag',
        keyvalue_key: 'Chave',
        keyvalue_value: 'Valor',
        keyvalue_add_row_label: '+ Adicionar linha',
        keyvalue_remove_row: 'Remover linha :number',
        code_enter_fullscreen: 'Entrar em tela cheia',
        code_fullscreen_short: 'Tela cheia',
        wizard_empty: 'Nenhuma etapa do assistente configurada.',
        wizard_step_label: 'Etapa :number: :label',
      },
    },
  },
};

function setLocale(dict: typeof ptBr | null): void {
  usePageMock.mockReturnValue({ props: dict === null ? {} : { i18n: dict } });
}

function field(type: string, props: Record<string, unknown>, extra: Record<string, unknown> = {}) {
  return { name: 'fld', type, label: 'Field', props, ...extra } as unknown as FieldSchema;
}

const noop = () => {};

afterEach(() => {
  setLocale(null);
});

describe('advanced field editors — i18n', () => {
  it('TagsInput chip-remove falls back to the English literal', () => {
    setLocale(null);
    render(
      <TagsInput
        field={field('tags', {}, {})}
        value={['alpha']}
        onChange={noop}
        errors={undefined}
        disabled={false}
        inputId="tags"
        describedBy={undefined}
      />,
    );
    expect(screen.getByLabelText('Remove tag alpha')).toBeInTheDocument();
  });

  it('TagsInput chip-remove localizes under pt_BR with the tag interpolated', () => {
    setLocale(ptBr);
    render(
      <TagsInput
        field={field('tags', {}, {})}
        value={['alpha']}
        onChange={noop}
        errors={undefined}
        disabled={false}
        inputId="tags2"
        describedBy={undefined}
      />,
    );
    expect(screen.getByLabelText('Remover tag alpha')).toBeInTheDocument();
  });

  it('KeyValueInput localizes header labels, add/remove controls under pt_BR', () => {
    setLocale(ptBr);
    render(
      <KeyValueInput
        field={field('keyValue', {})}
        value={[{ key: 'a', value: 'b' }]}
        onChange={noop}
        errors={undefined}
        disabled={false}
        inputId="kv"
        describedBy={undefined}
      />,
    );
    expect(screen.getByText('Chave')).toBeInTheDocument();
    expect(screen.getByText('Valor')).toBeInTheDocument();
    expect(screen.getByText('+ Adicionar linha')).toBeInTheDocument();
    expect(screen.getByLabelText('Remover linha 1')).toBeInTheDocument();
  });

  it('KeyValueInput keeps English defaults when no dictionary is present', () => {
    setLocale(null);
    render(
      <KeyValueInput
        field={field('keyValue', {})}
        value={[{ key: 'a', value: 'b' }]}
        onChange={noop}
        errors={undefined}
        disabled={false}
        inputId="kv2"
        describedBy={undefined}
      />,
    );
    expect(screen.getByText('Key')).toBeInTheDocument();
    expect(screen.getByText('Value')).toBeInTheDocument();
    expect(screen.getByText('+ Add row')).toBeInTheDocument();
    expect(screen.getByLabelText('Remove row 1')).toBeInTheDocument();
  });

  it('CodeInput fullscreen toggle localizes aria-label + visible text under pt_BR', () => {
    setLocale(ptBr);
    render(
      <CodeInput
        field={field('code', { language: 'plaintext', lineNumbers: false })}
        value=""
        onChange={noop}
        errors={undefined}
        disabled={false}
        inputId="code"
        describedBy={undefined}
      />,
    );
    expect(screen.getByLabelText('Entrar em tela cheia')).toBeInTheDocument();
    expect(screen.getByText('Tela cheia')).toBeInTheDocument();
  });

  it('WizardInput empty-state localizes under pt_BR', () => {
    setLocale(ptBr);
    render(
      <WizardInput
        field={field('wizard', { steps: [] })}
        value={{}}
        onChange={noop}
        errors={undefined}
        disabled={false}
        inputId="wiz"
        describedBy={undefined}
      />,
    );
    expect(screen.getByText('Nenhuma etapa do assistente configurada.')).toBeInTheDocument();
  });

  it('WizardInput step aria-label localizes under pt_BR', () => {
    setLocale(ptBr);
    render(
      <WizardInput
        field={field('wizard', {
          steps: [{ name: 's1', label: 'Profile', schema: [] }],
          skippable: true,
        })}
        value={{}}
        onChange={noop}
        errors={undefined}
        disabled={false}
        inputId="wiz2"
        describedBy={undefined}
      />,
    );
    expect(screen.getByLabelText('Etapa 1: Profile')).toBeInTheDocument();
  });
});
