import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { resolveLocale, t } from '../i18n.js';

const LOCALE_VARS = ['LC_ALL', 'LC_MESSAGES', 'LANG'] as const;

describe('cli-ink i18n', () => {
  let saved: Record<string, string | undefined>;

  beforeEach(() => {
    saved = {};
    for (const key of LOCALE_VARS) {
      saved[key] = process.env[key];
      delete process.env[key];
    }
  });

  afterEach(() => {
    for (const key of LOCALE_VARS) {
      const value = saved[key];
      if (value === undefined) delete process.env[key];
      else process.env[key] = value;
    }
  });

  it('defaults to English when no locale env is set', () => {
    expect(resolveLocale()).toBe('en');
    expect(t('cli.dashboard.title')).toBe('Arqel Dashboard');
    expect(t('cli.dashboard.loading')).toBe('Loading dashboard…');
  });

  it('localizes to pt_BR when LANG is a Brazilian Portuguese locale', () => {
    process.env['LANG'] = 'pt_BR.UTF-8';
    expect(resolveLocale()).toBe('pt_BR');
    expect(t('cli.dashboard.title')).toBe('Painel Arqel');
    expect(t('cli.dashboard.loading')).toBe('Carregando painel…');
    expect(t('cli.resources.empty')).toBe('Nenhum recurso encontrado.');
    expect(t('cli.logs.following')).toBe('(acompanhando)');
  });

  it('prefers LC_ALL over LANG', () => {
    process.env['LANG'] = 'en_US.UTF-8';
    process.env['LC_ALL'] = 'pt_BR.UTF-8';
    expect(resolveLocale()).toBe('pt_BR');
    expect(t('cli.error.prefix')).toBe('Erro:');
  });

  it('falls back to English, then the provided fallback, then the key', () => {
    process.env['LANG'] = 'pt_BR.UTF-8';
    // Unknown key -> provided fallback.
    expect(t('cli.unknown.key', 'My fallback')).toBe('My fallback');
    // Unknown key, no fallback -> the key itself.
    expect(t('cli.totally.unknown')).toBe('cli.totally.unknown');
  });

  it('interpolates :placeholder tokens', () => {
    expect(t('cli.unknown.key', 'Found :count items', { count: 3 })).toBe('Found 3 items');
  });
});
