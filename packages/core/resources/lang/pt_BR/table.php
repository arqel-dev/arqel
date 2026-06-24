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
        // Nome acessível do landmark <nav> de paginação.
        'label' => 'Paginação',
    ],
    'column' => [
        // Nome acessível do cabeçalho (vazio) da coluna de ações da linha.
        'actions' => 'Ações',
    ],
    'group' => [
        // Rótulo do cabeçalho do grupo sintético exibido quando a tabela não tem groupBy.
        'all' => 'Todos',
    ],
    'sort' => [
        'asc' => 'Crescente',
        'desc' => 'Decrescente',
    ],
    'boolean' => [
        // Nomes acessíveis (aria-label) para o glifo neutro do boolean cell
        // (✓ / —). Anunciados por leitores de tela no lugar de 'true'/'false'.
        'true_label' => 'sim',
        'false_label' => 'não',
    ],
    'filters' => [
        'apply' => 'Aplicar',
        'reset' => 'Limpar',
        'all' => 'Todos',
        'yes' => 'Sim',
        'no' => 'Não',
        'clear' => 'Limpar filtros (:count)',
        // Nome acessível da legenda (sr-only) do <fieldset> de filtros.
        'legend' => 'Filtros',
        // Nomes acessíveis dos dois campos de intervalo de datas (:label é o
        // rótulo do filtro).
        'date_from' => ':label de',
        'date_to' => ':label até',
    ],
    'bulk' => [
        // Pluralizável: o tradutor JS escolhe a forma via Intl.PluralRules
        // ({one}/{other}), então "1 selecionado" nunca vira "1 selecionado(s)".
        'selected' => '{one} :count selecionado|{other} :count selecionados',
        'select_all' => 'Selecionar todos',
        // Nome acessível do checkbox de selecionar-tudo no cabeçalho (vs o rótulo curto do menu).
        'select_all_rows' => 'Selecionar todas as linhas',
        'clear' => 'Limpar',
        // Nome acessível do landmark <section> de ações em massa.
        'label' => 'Ações em massa',
        // Nome acessível do checkbox de seleção de cada linha (:id é a chave).
        'select_row' => 'Selecionar linha :id',
    ],
];
