<?php

declare(strict_types=1);

return [
    'empty' => 'Nenhum registro encontrado.',
    'loading' => 'Carregando…',
    'per_page' => 'Por página',
    'search' => [
        'label' => 'Pesquisar',
        'placeholder' => 'Pesquisar...',
        // Placeholder com o recurso, ex.: "Pesquisar posts…".
        'placeholder_for' => 'Pesquisar :resource…',
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
        // Resumo compacto do intervalo (visível + anunciado).
        'range' => ':from–:to de :total',
    ],
    'sort' => [
        'asc' => 'Crescente',
        'desc' => 'Decrescente',
    ],
    'filters' => [
        'apply' => 'Aplicar',
        'reset' => 'Limpar',
        'all' => 'Todos',
        'yes' => 'Sim',
        'no' => 'Não',
        'clear' => 'Limpar filtros (:count)',
    ],
    'bulk' => [
        'selected' => ':count selecionado(s)',
        'select_all' => 'Selecionar todos',
        'clear' => 'Limpar',
    ],
];
