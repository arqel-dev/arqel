<?php

declare(strict_types=1);

return [
    'empty' => 'Nenhum registro encontrado.',
    'per_page' => 'Por página',
    'search' => [
        'label' => 'Pesquisar',
        'placeholder' => 'Pesquisar...',
    ],
    'pagination' => [
        // Rótulos curtos dos botões (texto visível).
        'previous' => 'Anterior',
        'next' => 'Próxima',
        // Nomes acessíveis descritivos (aria-label) — distintos dos rótulos
        // curtos para que leitores de tela anunciem a ação completa.
        'previous_page' => 'Página anterior',
        'next_page' => 'Próxima página',
        'showing' => 'Exibindo :from a :to de :total resultados',
    ],
    'sort' => [
        'asc' => 'Crescente',
        'desc' => 'Decrescente',
    ],
    'filters' => [
        'apply' => 'Aplicar',
        'reset' => 'Limpar',
        'all' => 'Todos',
        'clear' => 'Limpar filtros (:count)',
    ],
    'bulk' => [
        'selected' => ':count selecionado(s)',
        'select_all' => 'Selecionar todos',
        'clear' => 'Limpar',
    ],
];
