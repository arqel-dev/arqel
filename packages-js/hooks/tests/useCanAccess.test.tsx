import { renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { useCanAccess } from '../src/useCanAccess.js';
import { resetMockPage, setMockPage } from './setup.js';

afterEach(() => {
  resetMockPage();
});

describe('useCanAccess', () => {
  it('returns false when no `auth.can` is defined', () => {
    setMockPage({ props: {} });
    const { result } = renderHook(() => useCanAccess('manageBilling'));
    expect(result.current).toBe(false);
  });

  it('reads global abilities from `auth.can`', () => {
    setMockPage({
      props: {
        auth: { can: { manageBilling: true, exportData: false } },
      },
    });

    expect(renderHook(() => useCanAccess('manageBilling')).result.current).toBe(true);
    expect(renderHook(() => useCanAccess('exportData')).result.current).toBe(false);
    expect(renderHook(() => useCanAccess('unknownAbility')).result.current).toBe(false);
  });

  it('record-level abilities take precedence over global', () => {
    setMockPage({
      props: { auth: { can: { update: true } } },
    });

    const record = { can: { update: false } };
    const { result } = renderHook(() => useCanAccess('update', record));
    expect(result.current).toBe(false);
  });

  it('falls back to global when record has no `can` for that ability', () => {
    setMockPage({
      props: { auth: { can: { update: true } } },
    });

    const record = { can: { delete: false } };
    const { result } = renderHook(() => useCanAccess('update', record));
    expect(result.current).toBe(true);
  });

  it('falls back to global when record is null/undefined', () => {
    setMockPage({
      props: { auth: { can: { update: true } } },
    });

    expect(renderHook(() => useCanAccess('update', null)).result.current).toBe(true);
    expect(renderHook(() => useCanAccess('update', undefined)).result.current).toBe(true);
  });

  it('treats non-boolean values as false', () => {
    setMockPage({
      props: { auth: { can: { canExport: 'truthy' as unknown as boolean } } },
    });

    const { result } = renderHook(() => useCanAccess('canExport'));
    expect(result.current).toBe(false);
  });
});
