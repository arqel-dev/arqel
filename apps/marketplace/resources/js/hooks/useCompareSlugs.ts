import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'arqel:compare:slugs';
const MAX_SLUGS = 3;

function readFromStorage(): string[] {
  if (typeof window === 'undefined') return [];
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (raw === null) return [];
    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    return parsed.filter((value): value is string => typeof value === 'string').slice(0, MAX_SLUGS);
  } catch {
    return [];
  }
}

export type UseCompareSlugs = {
  slugs: string[];
  addSlug: (slug: string) => void;
  removeSlug: (slug: string) => void;
  clear: () => void;
  isFull: boolean;
};

export function useCompareSlugs(): UseCompareSlugs {
  const [slugs, setSlugs] = useState<string[]>(() => readFromStorage());

  useEffect(() => {
    if (typeof window === 'undefined') return;
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(slugs));
    } catch {
      // ignore quota errors
    }
  }, [slugs]);

  const addSlug = useCallback((slug: string): void => {
    setSlugs((prev) => {
      if (prev.includes(slug)) return prev;
      if (prev.length >= MAX_SLUGS) return prev;
      return [...prev, slug];
    });
  }, []);

  const removeSlug = useCallback((slug: string): void => {
    setSlugs((prev) => prev.filter((s) => s !== slug));
  }, []);

  const clear = useCallback((): void => {
    setSlugs([]);
  }, []);

  return { slugs, addSlug, removeSlug, clear, isFull: slugs.length >= MAX_SLUGS };
}
