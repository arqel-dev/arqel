import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { App } from '../App';
import { FieldsInspector } from '../FieldsInspector';
import { normalizeLocale, t } from '../i18n';

function mockLanguage(tag: string): void {
  vi.spyOn(navigator, 'language', 'get').mockReturnValue(tag);
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('i18n.normalizeLocale', () => {
  it('maps pt and pt-BR variants to pt_BR', () => {
    expect(normalizeLocale('pt')).toBe('pt_BR');
    expect(normalizeLocale('pt-BR')).toBe('pt_BR');
    expect(normalizeLocale('pt_BR')).toBe('pt_BR');
    expect(normalizeLocale('PT-br')).toBe('pt_BR');
  });

  it('defaults to en for unknown, empty, or nullish tags', () => {
    expect(normalizeLocale('en-US')).toBe('en');
    expect(normalizeLocale('fr')).toBe('en');
    expect(normalizeLocale('')).toBe('en');
    expect(normalizeLocale(null)).toBe('en');
    expect(normalizeLocale(undefined)).toBe('en');
  });
});

describe('i18n.t', () => {
  it('returns the English literal by default', () => {
    mockLanguage('en-US');
    expect(t('devtools.tab.policies', 'Policies')).toBe('Policies');
  });

  it('returns the pt_BR translation under a pt navigator.language', () => {
    mockLanguage('pt-BR');
    expect(t('devtools.tab.policies', 'Policies')).toBe('Políticas');
  });

  it('returns the provided fallback when the key is missing', () => {
    mockLanguage('pt-BR');
    expect(t('devtools.does.not.exist', 'Fallback value')).toBe('Fallback value');
  });

  it('interpolates :placeholder tokens', () => {
    mockLanguage('en-US');
    expect(
      t('devtools.policy.counter', ':allow allowed / :deny denied', {
        allow: 2,
        deny: 1,
      }),
    ).toBe('2 allowed / 1 denied');
  });
});

describe('panel localization', () => {
  it('renders English tab labels by default', () => {
    mockLanguage('en-US');
    render(<App version="0.10.0" />);
    expect(screen.getByTestId('top-tab-policies')).toHaveTextContent('Policies');
    expect(screen.getByTestId('top-tab-fields')).toHaveTextContent('Fields');
  });

  it('localizes tab labels under a pt_BR navigator.language', () => {
    mockLanguage('pt-BR');
    render(<App version="0.10.0" />);
    expect(screen.getByTestId('top-tab-policies')).toHaveTextContent('Políticas');
    expect(screen.getByTestId('top-tab-fields')).toHaveTextContent('Campos');
  });

  it('localizes the FieldsInspector type filter aria + option', () => {
    mockLanguage('pt-BR');
    render(<FieldsInspector fields={[{ name: 'title', type: 'text' }]} />);
    expect(screen.getByTestId('fields-type-filter')).toHaveAttribute(
      'aria-label',
      'Filtrar por tipo',
    );
    expect(screen.getByText('Todos os tipos')).toBeInTheDocument();
  });
});
