<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title inertia>Arqel Demo</title>
    {{-- @arqel-dev/theme: FOUC prevention. Aplica .dark em <html> antes do React montar.
         Lê localStorage 'arqel-theme' + system preference. Falhas silenciosas. --}}
    <script>(function(){try{var k="arqel-theme",t=null;try{t=localStorage.getItem(k)}catch(e){}if(t!=="light"&&t!=="dark"&&t!=="system")t="system";var r=t==="system"?(window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"):t;var el=document.documentElement;if(r==="dark")el.classList.add("dark");else el.classList.remove("dark");el.style.colorScheme=r;}catch(e){}})();</script>
    @inertiaHead
</head>
<body class="bg-[var(--arqel-color-bg)] text-[var(--arqel-color-fg)] antialiased">
    @inertia
</body>
</html>
