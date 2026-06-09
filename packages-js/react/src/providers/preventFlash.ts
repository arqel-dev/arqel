export interface PreventFlashOptions {
  storageKey?: string;
  darkClass?: string;
  attribute?: 'class' | 'data-theme';
}

const DEFAULT_STORAGE_KEY = 'arqel-theme';

/**
 * Returns a JS snippet (string) to inline in the document `<head>`
 * **before** the React bundles. Reads localStorage + system preference
 * and applies the `dark` class on `<html>` immediately, avoiding a
 * theme flash on load (FOUC).
 *
 * Pairs with {@link ThemeProvider}: both share the `arqel-theme`
 * storage key and `dark` class defaults (issue #236 — single context).
 *
 * The snippet is a self-failing IIFE — never corrupts the page even if
 * localStorage is blocked.
 */
export function preventFlashScript(options: PreventFlashOptions = {}): string {
  const { storageKey = DEFAULT_STORAGE_KEY, darkClass = 'dark', attribute = 'class' } = options;

  const k = JSON.stringify(storageKey);
  const c = JSON.stringify(darkClass);
  const a = JSON.stringify(attribute);

  return [
    '(function(){try{',
    'var k=',
    k,
    ',c=',
    c,
    ',a=',
    a,
    ';',
    'var t=null;try{t=localStorage.getItem(k);}catch(e){}',
    'if(t!=="light"&&t!=="dark"&&t!=="system")t="system";',
    'var r=t;',
    'if(t==="system"){',
    'r=(window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches)?"dark":"light";',
    '}',
    'var el=document.documentElement;',
    'if(a==="class"){if(r==="dark")el.classList.add(c);else el.classList.remove(c);}',
    'else{el.setAttribute("data-theme",r);}',
    'el.style.colorScheme=r;',
    '}catch(e){}})();',
  ].join('');
}
