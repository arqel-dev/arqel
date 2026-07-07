<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Relations;

use Arqel\Core\Relations\RelationManager;

final class CommentsRelationManager extends RelationManager
{
    public static string $relationship = 'comments';

    /**
     * Declares one always-visible column (`body`) and one always-redacted
     * column (`secret`, the `canSee(fn () => false)` equivalent) so
     * `RelationIndexTest` can prove `RelationController::index()` runs
     * related records through `InertiaDataBuilder::applyColumnSerialization()`
     * — review finding I1. Neither column being declared here changes what
     * `$related->get()` returns (redaction only strips the *payload* key),
     * so this fixture change is additive and doesn't affect any other test
     * in this suite.
     */
    public function table(): mixed
    {
        return new StubRelationTable([
            new StubRelationColumn('body', visible: true),
            new StubRelationColumn('secret', visible: false),
        ]);
    }

    /**
     * A single required `body` field, used to prove `create()` serialises
     * the manager's field schema and `store()` builds validation rules
     * from it.
     *
     * `arqel-dev/core` does not depend on `arqel-dev/fields`/`arqel-dev/form`
     * (see `RelationManager::fields()` doc block), and neither package is
     * installed in `core`'s own test suite — so this can't be a real
     * `Arqel\Fields\FieldFactory` field. It's also not consumable by the
     * real `Arqel\Form\FieldRulesExtractor::extract()`, which hard
     * `instanceof Field`-checks each entry rather than duck-typing: a stub
     * here is filtered out, not converted into a rule. `StubField` exists
     * only to prove `FieldSchemaSerializer::serialize()` (duck-typed) picks
     * up its shape for `create()`; see `RelationStoreTest.php`'s top-of-file
     * doc block for why the `store()` validation-rejection path is proven
     * by code review rather than a live HTTP test in this package.
     *
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            new StubField('body', required: true),
        ];
    }
}
