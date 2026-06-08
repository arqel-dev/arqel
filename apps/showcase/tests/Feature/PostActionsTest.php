<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Arqel\Resources\PostResource;
use Tests\TestCase;

/**
 * Structural assertions for the custom Actions exercised by Task 3.1:
 * the `publish` + `change_status` row actions and the `archive` bulk
 * action must be registered on the PostResource table, alongside the
 * pre-existing edit/delete/export defaults.
 */
final class PostActionsTest extends TestCase
{
    public function test_post_resource_exposes_custom_row_actions(): void
    {
        $table = (new PostResource)->table();

        $rowNames = array_map(
            static fn ($action): string => $action->getName(),
            $table->getActions(),
        );

        $this->assertContains('publish', $rowNames);
        $this->assertContains('change_status', $rowNames);
    }

    public function test_post_resource_exposes_archive_bulk_action(): void
    {
        $table = (new PostResource)->table();

        $bulkNames = array_map(
            static fn ($action): string => $action->getName(),
            $table->getBulkActions(),
        );

        $this->assertContains('archive', $bulkNames);
    }
}
