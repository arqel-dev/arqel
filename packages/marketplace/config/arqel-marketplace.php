<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Habilitação global
    |--------------------------------------------------------------------------
    |
    | Quando `false`, o `MarketplaceServiceProvider` ainda é booteado, mas
    | as rotas REST não são registradas. Útil para apps que querem só
    | consumir os models (e.g., scripts CLI ou jobs internos).
    */
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Prefixo de rotas REST
    |--------------------------------------------------------------------------
    |
    | Prefixo aplicado a todos os endpoints do marketplace. Exemplo:
    | `api/marketplace/plugins` → lista de plugins published.
    */
    'route_prefix' => 'api/marketplace',

    /*
    |--------------------------------------------------------------------------
    | Paginação default
    |--------------------------------------------------------------------------
    |
    | Number of items per page when `?per_page` não é passado pelo client.
    | O list controller clampa o valor recebido em [1, 100].
    */
    'pagination' => 20,

    /*
    |--------------------------------------------------------------------------
    | Submission review obrigatório
    |--------------------------------------------------------------------------
    |
    | Quando `true` (default), plugins entram com status `pending` e
    | precisam de aprovação manual antes de virar `published`. MKTPLC-002
    | implementa o submission workflow que consome esta flag.
    */
    'submission_review_required' => true,
];
