<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Arqel\Resources\PostResource;
use Arqel\Form\Layout\Tabs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Structural assertions for Task 3.2: the PostResource form() must use a
 * Tabs layout (Tabs/Grid/Group + visibleIf) and the table() must expose the
 * computed `word_count` column plus the `author` relationship column.
 */
final class PostFormTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_schema_uses_tabs_layout(): void
    {
        $schema = (new PostResource)->form()->getSchema();

        $hasTabs = false;
        foreach ($schema as $entry) {
            if ($entry instanceof Tabs) {
                $hasTabs = true;

                break;
            }
        }

        $this->assertTrue(
            $hasTabs,
            'PostResource form() must include a Tabs layout component.',
        );
    }

    public function test_table_exposes_computed_and_relationship_columns(): void
    {
        $columns = (new PostResource)->table()->getColumns();

        $names = array_map(
            static fn ($column): string => $column->getName(),
            $columns,
        );

        $this->assertContains('word_count', $names);
        $this->assertContains('author', $names);
    }
}
