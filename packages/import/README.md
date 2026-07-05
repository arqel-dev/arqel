# arqel-dev/import

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.3-777bb4.svg)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/laravel-%5E12.0%20%7C%20%5E13.0-ff2d20.svg)](https://laravel.com)
[![Status](https://img.shields.io/badge/status-alpha-yellow.svg)](#)

Pacote de **Imports** para o ecossistema [Arqel](https://arqel.dev) — pipeline de importação CSV/XLSX de primeira classe, com validação por-linha, processamento assíncrono e CSV de linhas falhadas para download.

## Status

**Alpha** — `Importer`, `ImportColumn`, `ProcessImportJob`, `ImportAction` e os controllers de upload/download entregues.

## Instalação

```bash
composer require arqel-dev/import
```

## Uso

Declare um `Importer` para o seu model:

```php
use Arqel\Import\Importer;
use Arqel\Import\ImportColumn;
use App\Models\User;

final class UserImporter extends Importer
{
    public static string $model = User::class;

    public function columns(): array
    {
        return [
            ImportColumn::make('name')->rules(['required', 'string', 'max:255']),
            ImportColumn::make('email')->rules(['required', 'email', 'unique:users,email']),
        ];
    }
}
```

E ligue-o a uma action no seu `Resource`:

```php
use Arqel\Import\Actions\ImportAction;

ImportAction::make('import')->importer(UserImporter::class);
```

## Convenções

- `declare(strict_types=1)` em todos os arquivos
- Classes `final` por default (exceto `Importer`, ponto de extensão)
- Processamento sempre assíncrono via `ProcessImportJob` — nunca sync na request HTTP
- Requer `ext-zip` para importação XLSX (CSV funciona sem)

## Links

- [Documentação](https://arqel.dev/docs/import) — em construção
- [SKILL.md](./SKILL.md) — contratos e convenções detalhadas
- [CHANGELOG](../../CHANGELOG.md) — entrada `import (novo pacote)`
