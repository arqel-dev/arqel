<?php

declare(strict_types=1);

return [
    // Placeholder do input do palette de comandos (também a dica acessível).
    'placeholder' => 'Digite um comando…',

    // Mensagem de resultados vazios exibida quando nenhum comando corresponde à busca.
    'no_results' => 'Nenhum comando encontrado',

    // Categorias dos command providers internos (cabeçalhos de grupo no palette).
    'category' => [
        'navigation' => 'Navegação',
        'settings' => 'Configurações',
    ],

    // NavigationCommandProvider — rótulo do comando "Ir para {rótulo plural}".
    'go_to' => 'Ir para :label',

    // ThemeCommandProvider — rótulos dos comandos de troca de tema.
    'theme' => [
        'light' => 'Mudar para tema claro',
        'dark' => 'Mudar para tema escuro',
        'system' => 'Usar tema do sistema',
    ],
];
