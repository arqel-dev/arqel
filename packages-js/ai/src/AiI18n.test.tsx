import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { setMockTranslations } from '../tests/setup.js';
import { AiExtractInput } from './AiExtractInput.js';
import { AiImageInput } from './AiImageInput.js';
import { AiSelectInput } from './AiSelectInput.js';
import { AiTextInput } from './AiTextInput.js';
import { AiTranslateInput } from './AiTranslateInput.js';

/**
 * Localization coverage for the @arqel-dev/ai field renderers. Each test
 * installs a pt_BR dictionary into the mocked Inertia `i18n` prop and asserts
 * the renderer routes its default labels / errors / status aria through
 * `useArqelTranslations()` instead of the hardcoded English literals.
 */

const aiDict = {
  arqel: {
    ai: {
      generate: 'Gerar com IA',
      regenerate: 'Gerar novamente',
      classify: 'Classificar com IA',
      extract: 'Extrair com IA',
      analyze: 'Analisar com IA',
      apply: 'Aplicar',
      apply_field: 'Aplicar :field',
      apply_all: 'Aplicar tudo',
      translate_all_missing: 'Traduzir tudo que falta',
      translate_from: 'Traduzir de :language',
      source: 'Fonte: :field',
      select_placeholder: 'Selecionar...',
      image_file: 'Arquivo de imagem',
      missing_translation: 'Tradução ausente',
      error_http: 'Falha na geração (HTTP :status).',
      error_network: 'Falha na geração: erro de rede.',
      classify_error_http: 'Falha na classificação (HTTP :status).',
      classify_error_none: 'Não foi possível classificar.',
      classify_error_network: 'Falha na classificação: erro de rede.',
      extract_error_http: 'Falha na extração (HTTP :status).',
      extract_error_invalid: 'Falha na extração: corpo de resposta inválido.',
      extract_error_network: 'Falha na extração: erro de rede.',
      analyze_error_http: 'Falha na análise (HTTP :status).',
      analyze_error_invalid: 'Falha na análise: corpo de resposta inválido.',
      analyze_error_network: 'Falha na análise: erro de rede.',
      translate_error_http: 'Falha na tradução (HTTP :status).',
      translate_error_invalid: 'Falha na tradução: corpo de resposta inválido.',
      translate_error_network: 'Falha na tradução: erro de rede.',
      file_too_large: 'Arquivo muito grande: :size (máx. :max).',
      missing_translate_url:
        'URL de tradução ausente: forneça `translateUrl` ou ambos `resource` e `field`.',
      missing_classify_url:
        'URL de classificação ausente: forneça `classifyUrl` ou ambos `resource` e `field`.',
      missing_generate_url:
        'URL de geração ausente: forneça `generateUrl` ou ambos `resource` e `field`.',
      missing_extract_url:
        'URL de extração ausente: forneça `extractUrl` ou ambos `resource` e `field`.',
      missing_analyze_url:
        'URL de análise ausente: forneça `analyzeUrl` ou ambos `resource` e `field`.',
      selected_preview_alt: 'Pré-visualização selecionada',
      status_generating: 'Gerando',
      status_classifying: 'Classificando',
      status_extracting: 'Extraindo',
      status_analyzing: 'Analisando',
      status_translating: 'Traduzindo',
    },
  },
};

function makeFetchFail(status: number): ReturnType<typeof vi.fn> {
  return vi.fn(async () => ({ ok: false, status, json: async () => ({}) }));
}

describe('@arqel-dev/ai i18n', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('AiTextInput localizes the default generate button label', () => {
    setMockTranslations(aiDict);
    render(
      <AiTextInput
        name="bio"
        value=""
        props={{ provider: null, buttonLabel: '', maxLength: null, hasContextFields: false }}
        resource="users"
        field="bio"
      />,
    );
    expect(screen.getByRole('button', { name: 'Gerar com IA' })).toBeInTheDocument();
  });

  it('AiTextInput localizes the HTTP error banner', async () => {
    setMockTranslations(aiDict);
    vi.stubGlobal('fetch', makeFetchFail(500));
    render(
      <AiTextInput
        name="bio"
        value=""
        onChange={vi.fn()}
        props={{ provider: null, buttonLabel: '', maxLength: null, hasContextFields: false }}
        resource="users"
        field="bio"
      />,
    );
    fireEvent.click(screen.getByRole('button', { name: 'Gerar com IA' }));
    const alert = await screen.findByRole('alert');
    expect(alert.textContent).toBe('Falha na geração (HTTP 500).');
  });

  it('AiSelectInput localizes the classify button label and select placeholder', () => {
    setMockTranslations(aiDict);
    render(
      <AiSelectInput
        name="cat"
        value={null}
        props={{
          options: { a: 'A' },
          classifyFromFields: ['body'],
          provider: null,
          fallbackOption: null,
          hasContextFields: true,
        }}
        resource="posts"
        field="cat"
      />,
    );
    expect(screen.getByRole('button', { name: 'Classificar com IA' })).toBeInTheDocument();
    expect(screen.getByText('Selecionar...')).toBeInTheDocument();
  });

  it('AiExtractInput localizes the source label, extract button and apply-all', () => {
    setMockTranslations(aiDict);
    render(
      <AiExtractInput
        name="ex"
        value={{ title: 'Hello' }}
        props={{
          sourceField: 'raw',
          targetFields: ['title'],
          buttonLabel: '',
          usingJsonMode: false,
          provider: null,
        }}
        resource="docs"
        field="ex"
      />,
    );
    expect(screen.getByText('Fonte: raw')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Extrair com IA' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Aplicar tudo' })).toBeInTheDocument();
  });

  it('AiImageInput localizes the analyze button and image-file aria label', () => {
    setMockTranslations(aiDict);
    render(
      <AiImageInput
        name="img"
        value={null}
        props={{
          analyses: [],
          populateFields: {},
          provider: null,
          acceptedMimes: ['image/png'],
          maxFileSize: 0,
          buttonLabel: '',
        }}
        resource="media"
        field="img"
      />,
    );
    expect(screen.getByRole('button', { name: 'Analisar com IA' })).toBeInTheDocument();
    expect(screen.getByLabelText('Arquivo de imagem')).toBeInTheDocument();
  });

  it('AiTranslateInput localizes translate-all-missing and translate-from labels', () => {
    setMockTranslations(aiDict);
    render(
      <AiTranslateInput
        name="tr"
        value={{ en: 'Hi' }}
        props={{
          languages: ['en', 'pt'],
          defaultLanguage: 'en',
          autoTranslate: false,
          provider: null,
        }}
        resource="posts"
        field="tr"
      />,
    );
    expect(screen.getByRole('button', { name: 'Traduzir tudo que falta' })).toBeInTheDocument();
    // Switch to the non-default tab to reveal the per-language translate button.
    fireEvent.click(screen.getByRole('tab', { name: /pt/ }));
    expect(screen.getByRole('button', { name: 'Traduzir de en' })).toBeInTheDocument();
  });

  it('AiImageInput localizes the file-too-large validation error with :size/:max', () => {
    setMockTranslations(aiDict);
    render(
      <AiImageInput
        name="img"
        value={null}
        props={{
          analyses: [],
          populateFields: {},
          provider: null,
          acceptedMimes: ['image/png'],
          maxFileSize: 10,
          buttonLabel: '',
        }}
        resource="media"
        field="img"
      />,
    );
    const input = screen.getByLabelText('Arquivo de imagem') as HTMLInputElement;
    const big = new File(['x'.repeat(100)], 'big.png', { type: 'image/png' });
    fireEvent.change(input, { target: { files: [big] } });
    const alert = screen.getByRole('alert');
    expect(alert.textContent).toBe('Arquivo muito grande: 100 B (máx. 10 B).');
  });

  it('AiTranslateInput localizes the missing-translate-url config error', () => {
    setMockTranslations(aiDict);
    render(
      <AiTranslateInput
        name="tr"
        value={{ en: 'Hi' }}
        props={{
          languages: ['en', 'pt'],
          defaultLanguage: 'en',
          autoTranslate: false,
          provider: null,
        }}
      />,
    );
    fireEvent.click(screen.getByRole('button', { name: 'Traduzir tudo que falta' }));
    const alert = screen.getByRole('alert');
    expect(alert.textContent).toBe(
      'URL de tradução ausente: forneça `translateUrl` ou ambos `resource` e `field`.',
    );
  });

  it('AiSelectInput localizes the missing-classify-url config error', () => {
    setMockTranslations(aiDict);
    render(
      <AiSelectInput
        name="cat"
        value={null}
        props={{
          options: { a: 'A' },
          classifyFromFields: ['body'],
          provider: null,
          fallbackOption: null,
          hasContextFields: true,
        }}
      />,
    );
    fireEvent.click(screen.getByRole('button', { name: 'Classificar com IA' }));
    const alert = screen.getByRole('alert');
    expect(alert.textContent).toBe(
      'URL de classificação ausente: forneça `classifyUrl` ou ambos `resource` e `field`.',
    );
  });

  it('AiTextInput localizes the missing-generate-url config error', () => {
    setMockTranslations(aiDict);
    render(
      <AiTextInput
        name="bio"
        value=""
        onChange={vi.fn()}
        props={{ provider: null, buttonLabel: '', maxLength: null, hasContextFields: false }}
      />,
    );
    fireEvent.click(screen.getByRole('button', { name: 'Gerar com IA' }));
    const alert = screen.getByRole('alert');
    expect(alert.textContent).toBe(
      'URL de geração ausente: forneça `generateUrl` ou ambos `resource` e `field`.',
    );
  });

  it('AiExtractInput localizes the missing-extract-url config error', () => {
    setMockTranslations(aiDict);
    render(
      <AiExtractInput
        name="ex"
        value={{ title: 'Hello' }}
        props={{
          sourceField: 'raw',
          targetFields: ['title'],
          buttonLabel: '',
          usingJsonMode: false,
          provider: null,
        }}
      />,
    );
    fireEvent.click(screen.getByRole('button', { name: 'Extrair com IA' }));
    const alert = screen.getByRole('alert');
    expect(alert.textContent).toBe(
      'URL de extração ausente: forneça `extractUrl` ou ambos `resource` e `field`.',
    );
  });

  it('AiImageInput localizes the missing-analyze-url config error and preview alt', () => {
    setMockTranslations(aiDict);
    vi.stubGlobal('URL', {
      createObjectURL: vi.fn(() => 'blob:fake-preview'),
      revokeObjectURL: vi.fn(),
    });
    render(
      <AiImageInput
        name="img"
        value={null}
        props={{
          analyses: [],
          populateFields: {},
          provider: null,
          acceptedMimes: ['image/png'],
          maxFileSize: 0,
          buttonLabel: '',
        }}
      />,
    );
    const input = screen.getByLabelText('Arquivo de imagem') as HTMLInputElement;
    const file = new File(['x'], 'pic.png', { type: 'image/png' });
    fireEvent.change(input, { target: { files: [file] } });
    // Preview <img> alt is localized.
    expect(screen.getByAltText('Pré-visualização selecionada')).toBeInTheDocument();
    // Analyze without a resolvable URL surfaces the localized config error.
    fireEvent.click(screen.getByRole('button', { name: 'Analisar com IA' }));
    const alert = screen.getByRole('alert');
    expect(alert.textContent).toBe(
      'URL de análise ausente: forneça `analyzeUrl` ou ambos `resource` e `field`.',
    );
  });

  it('falls back to English literals when the dictionary lacks the ai keys', () => {
    setMockTranslations({}, 'en');
    render(
      <AiTextInput
        name="bio"
        value=""
        props={{ provider: null, buttonLabel: '', maxLength: null, hasContextFields: false }}
        resource="users"
        field="bio"
      />,
    );
    expect(screen.getByRole('button', { name: 'Generate with AI' })).toBeInTheDocument();
  });
});
