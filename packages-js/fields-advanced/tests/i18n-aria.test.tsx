/**
 * i18n accessibility regression suite for `@arqel-dev/fields-advanced`.
 *
 * Every editor control accessible name (aria-label / role=toolbar / role=menu)
 * across the rich-content editing surface must route through
 * `useArqelTranslations()` so screen-reader users in non-English locales hear a
 * localized name. Before the fix these were hardcoded English literals and each
 * assertion below would fail (the pt_BR override had no effect on the DOM).
 *
 * The shared dictionary is mocked under `props.i18n.translations` — the same
 * source `HandleArqelInertiaRequests` ships — so
 * `t('arqel.fields_advanced.markdown_bold')` resolves
 * `arqel.php => fields_advanced.markdown_bold`.
 */

import type { FieldSchema } from '@arqel-dev/types/fields';
import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { BuilderInput } from '../src/builder/BuilderInput.js';
import { MarkdownInput } from '../src/markdown/MarkdownInput.js';
import { RepeaterInput } from '../src/repeater/RepeaterInput.js';
import { RichTextInput } from '../src/rich-text/RichTextInput.js';
import { WizardInput } from '../src/wizard/WizardInput.js';

const { pageMock } = vi.hoisted(() => ({ pageMock: vi.fn(() => ({ props: {} })) }));
vi.mock('@inertiajs/react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/react')>();
  return { ...actual, usePage: pageMock };
});

const PT_BR = {
  arqel: {
    fields_advanced: {
      markdown_formatting: 'Formatação Markdown',
      markdown_bold: 'Negrito',
      markdown_italic: 'Itálico',
      markdown_heading: 'Título',
      markdown_code: 'Código em linha',
      markdown_link: 'Link',
      markdown_list: 'Lista',
      markdown_preview_open: 'Abrir pré-visualização',
      markdown_editor_mode: 'Modo do editor',
      markdown_preview: 'Pré-visualização Markdown',
      repeater_move_up: 'Mover para cima',
      repeater_move_down: 'Mover para baixo',
      repeater_add_item: 'Adicionar item',
      wizard_back: 'Voltar',
      wizard_submit: 'Enviar',
      wizard_next: 'Avançar',
      builder_close_picker: 'Fechar seletor de blocos',
      builder_add_block: 'Adicionar bloco',
      richtext_toolbar: 'Barra de formatação',
    },
  },
};

function usePtBr(): void {
  pageMock.mockReturnValue({
    props: { i18n: { locale: 'pt_BR', available: ['pt_BR'], translations: PT_BR } },
  });
}

afterEach(() => {
  pageMock.mockReset();
  pageMock.mockReturnValue({ props: {} });
});

function field(type: string, component: string, props: Record<string, unknown>): FieldSchema {
  return {
    type,
    name: 'fld',
    label: 'Field',
    component,
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
    props,
  } as unknown as FieldSchema;
}

describe('fields-advanced aria i18n', () => {
  it('localizes MarkdownInput toolbar control names', () => {
    usePtBr();
    render(
      <MarkdownInput
        field={field('markdown', 'MarkdownInput', { toolbar: true, preview: false })}
        value=""
        onChange={() => {}}
      />,
    );
    expect(screen.getByRole('toolbar', { name: 'Formatação Markdown' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Negrito' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Itálico' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Título' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Código em linha' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Link' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Lista' })).toBeInTheDocument();
  });

  it('keeps English MarkdownInput control names when no dictionary is present', () => {
    render(
      <MarkdownInput
        field={field('markdown', 'MarkdownInput', { toolbar: true, preview: false })}
        value=""
        onChange={() => {}}
      />,
    );
    expect(screen.getByRole('toolbar', { name: 'Markdown formatting' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Bold' })).toBeInTheDocument();
  });

  it('localizes RepeaterInput reorder + add-item control names', () => {
    usePtBr();
    render(
      <RepeaterInput
        field={field('repeater', 'RepeaterInput', {
          schema: [],
          reorderable: true,
          minItems: null,
          maxItems: null,
          itemLabel: null,
          collapsible: false,
          cloneable: false,
        })}
        value={[{}, {}]}
        onChange={() => {}}
      />,
    );
    expect(screen.getAllByRole('button', { name: 'Mover para cima' }).length).toBeGreaterThan(0);
    expect(screen.getAllByRole('button', { name: 'Mover para baixo' }).length).toBeGreaterThan(0);
    expect(screen.getByRole('button', { name: 'Adicionar item' })).toBeInTheDocument();
  });

  it('localizes WizardInput Back / Next control names', () => {
    usePtBr();
    render(
      <WizardInput
        field={field('wizard', 'WizardInput', {
          steps: [
            { name: 'one', label: 'One', schema: [] },
            { name: 'two', label: 'Two', schema: [] },
          ],
        })}
        value={{}}
        onChange={() => {}}
      />,
    );
    expect(screen.getByRole('button', { name: 'Voltar' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Avançar' })).toBeInTheDocument();
  });

  it('localizes BuilderInput add-block control name', () => {
    usePtBr();
    render(
      <BuilderInput
        field={field('builder', 'BuilderInput', {
          blocks: { text: { label: 'Text', schema: [] } },
          minItems: null,
          maxItems: null,
          reorderable: true,
          cloneable: false,
        })}
        value={[]}
        onChange={() => {}}
      />,
    );
    expect(screen.getAllByRole('button', { name: 'Adicionar bloco' }).length).toBeGreaterThan(0);
  });

  it('localizes RichTextInput formatting toolbar name', () => {
    usePtBr();
    render(
      <RichTextInput
        field={field('rich-text', 'RichTextInput', { toolbar: ['bold', 'italic'] })}
        value=""
        onChange={() => {}}
      />,
    );
    expect(screen.getByRole('toolbar', { name: 'Barra de formatação' })).toBeInTheDocument();
  });
});
