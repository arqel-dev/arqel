import { DEFAULT_STORAGE_KEY } from './storage';

export interface PreventFlashOptions {
  storageKey?: string;
  darkClass?: string;
  attribute?: 'class' | 'data-theme';
}

/**
 * Retorna um snippet JS (string) para inserir inline no `<head>` do HTML
 * **antes** dos bundles React. Lê localStorage + system pref e aplica
 * imediatamente a classe `dark` em `<html>`, evitando flash branco no
 * carregamento (FOUC).
 *
 * Uso (Blade):
 *   <script>{!! \Arqel\Theme\preventFlashScript() !!}</script>
 *
 * Uso direto:
 *   <script dangerouslySetInnerHTML={{ __html: preventFlashScript() }} />
 *
 * O snippet é IIFE que falha silenciosamente — nenhuma corrupção de página
 * mesmo se localStorage estiver bloqueado.
 */
export function preventFlashScript(options: PreventFlashOptions = {}): string {
  const { storageKey = DEFAULT_STORAGE_KEY, darkClass = 'dark', attribute = 'class' } = options;

  // JSON.stringify garante escape correto de aspas e quebras.
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
