<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Trait demonstrativa para integração com `arqel-dev/versioning`.
 *
 * Mantém um contador local de versões ao salvar — versão real do pacote
 * persiste snapshots em tabela `arqel_versions` e suporta restore.
 */
trait Versionable
{
    public int $versionCount = 0;

    public static function bootVersionable(): void
    {
        static::saved(static function ($model): void {
            $model->versionCount++;
        });
    }
}
