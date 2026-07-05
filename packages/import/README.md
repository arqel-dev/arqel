# @arqel-dev/import

> Pipeline de importação CSV/XLSX de primeira classe para o [Arqel](https://arqel.dev).

Lê um arquivo enviado pelo usuário (CSV ou XLSX), valida cada linha contra regras declarativas por coluna, persiste os registros válidos em chunks e gera um CSV de linhas falhadas para download — fecha o par simétrico do `arqel-dev/export`.

## Instalação

```bash
composer require arqel-dev/import
```

O provider `Arqel\Import\ImportServiceProvider` é auto-descoberto via `extra.laravel.providers`. Não há config nem migrations — o pacote não persiste um modelo `Import` próprio (veja a nota de `ImportLogger` abaixo).

## Uso básico

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
            ImportColumn::make('email')
                ->rules(['required', 'email'])
                ->requiredMapping(),
        ];
    }
}
```

```php
use Arqel\Import\Actions\ImportAction;

// Dentro de Resource::actions()
ImportAction::make('import')->importer(UserImporter::class);
```

Isso registra um botão de toolbar "Import" no Resource. O upload vai para `POST admin/imports`, dispara `ProcessImportJob` em fila, e — se houver linhas inválidas — o usuário pode baixar o CSV de falhas em `GET admin/imports/{importId}/failed-rows`.

Por padrão, o Gate `import` não é exigido (scaffold mode). Para restringir quem pode importar:

```php
Gate::define('import', fn ($user) => $user->can('manage-imports'));
```

## Documentação completa

Ver [`SKILL.md`](./SKILL.md) neste pacote para contratos, convenções e anti-patterns, ou [`arqel.dev/docs`](https://arqel.dev/docs) para a documentação do usuário final.

## Licença

MIT.
