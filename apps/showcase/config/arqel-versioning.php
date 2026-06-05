<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | Quando `false`, o trait `Versionable` torna-se no-op: nenhum snapshot
    | é gravado em `arqel_versions`. Útil em migrações em massa, seeders ou
    | environments onde versioning é redundante (réplicas read-only).
    */
    'enabled' => env('ARQEL_VERSIONING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Retention — keep_versions
    |--------------------------------------------------------------------------
    |
    | Limite de versions retidas por record. `0` = unbounded. Após gravar
    | uma nova version, o trait deleta as mais antigas que excederem o
    | limite (estratégia 'count'). Time-based pruning fica para VERS-006.
    */
    'keep_versions' => env('ARQEL_VERSIONING_KEEP_VERSIONS', 50),

    /*
    |--------------------------------------------------------------------------
    | Prune strategy
    |--------------------------------------------------------------------------
    |
    | 'count' — mantém apenas as N versions mais recentes (default).
    | 'time'  — placeholder para VERS-006 (cleanup job + max_age_days).
    */
    'prune_strategy' => env('ARQEL_VERSIONING_PRUNE_STRATEGY', 'count'),

    /*
    |--------------------------------------------------------------------------
    | Audit user identifier
    |--------------------------------------------------------------------------
    |
    | Callable (FQCN::method ou Closure registered via container) que
    | resolve o ID do user a gravar em `created_by_user_id`. Quando
    | `null`, o trait usa `auth()->id()`. Útil para CLI / queue jobs onde
    | `Auth` não está hidratado.
    */
    'audit_user' => env('ARQEL_VERSIONING_AUDIT_USER'),

    /*
    |--------------------------------------------------------------------------
    | User model
    |--------------------------------------------------------------------------
    |
    | Lido pelo relacionamento `Version::user()`. Devolve `null` quando a
    | classe não existe ou não é um Eloquent `Model` — defensive para
    | apps minimalistas / testes.
    */
    'user_model' => env('ARQEL_VERSIONING_USER_MODEL', 'App\\Models\\User'),
];
