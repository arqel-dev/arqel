<?php

declare(strict_types=1);

namespace Arqel\Core\CommandPalette\Providers;

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\CommandProvider;
use Arqel\Core\Panel\PanelRegistry;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\ResourceAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Built-in command provider that searches *records* across every
 * globally-searchable Resource and emits one Command per hit.
 *
 * For each Resource whose {@see \Arqel\Core\Resources\Concerns\HasGlobalSearch::globallySearchable()}
 * is non-empty and whose `viewAny` Policy allows the current user, the
 * provider runs a bounded `LIKE` query over the declared attributes and
 * turns each row into a Command linking to the record's edit page.
 *
 * Records carry a fixed {@see self::RECORD_RANK_SCORE} so the palette's
 * FuzzyMatcher never drops a row that matched in SQL on a non-title
 * column (records already come pre-filtered by the database).
 *
 * Every per-resource read is wrapped in try/catch: a misbehaving or
 * mis-declared Resource (missing model, unknown column) is skipped so
 * one bad Resource never brings down the palette.
 */
final class RecordSearchCommandProvider implements CommandProvider
{
    public const int MIN_TERM_LENGTH = 2;

    public const int PER_RESOURCE_LIMIT = 5;

    public const int RECORD_RANK_SCORE = 60;

    public function __construct(
        private readonly ResourceRegistry $registry,
    ) {}

    /**
     * @return array<int, Command>
     */
    public function provide(?Authenticatable $user, string $query): array
    {
        $term = trim($query);

        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return [];
        }

        $panelPath = $this->resolvePanelPath();
        $escaped = addcslashes($term, '%_\\');
        $commands = [];

        foreach ($this->registry->all() as $resourceClass) {
            if (ResourceAuthorization::viewAnyDenied($resourceClass, $user)) {
                continue;
            }

            foreach ($this->searchResource($resourceClass, $escaped) as $record) {
                $command = $this->buildCommand($resourceClass, $record, $panelPath);
                if ($command !== null) {
                    $commands[] = $command;
                }
            }
        }

        return $commands;
    }

    /**
     * Bounded LIKE query over the Resource's searchable attributes.
     * Any failure (no model, unknown column) yields an empty result
     * for that Resource instead of bubbling up.
     *
     * @param class-string $resourceClass
     *
     * @return iterable<int, Model>
     */
    private function searchResource(string $resourceClass, string $escaped): iterable
    {
        try {
            $attributes = $resourceClass::globallySearchable();

            if ($attributes === []) {
                return [];
            }

            /** @var class-string<Model> $modelClass */
            $modelClass = $resourceClass::getModel();

            return $modelClass::query()
                ->where(function (Builder $sub) use ($attributes, $escaped): void {
                    foreach ($attributes as $column) {
                        // Raw `ESCAPE '\'` clause: SQLite's LIKE ignores
                        // backslash-escaping unless an ESCAPE clause is
                        // present (MySQL/Postgres default to it), so without
                        // this a literal "%"/"_" in the search term would
                        // still act as a wildcard on sqlite. The pattern
                        // value itself stays a bound parameter — never
                        // concatenated into the SQL string.
                        $sub->orWhereRaw(
                            $sub->getGrammar()->wrap($column)." LIKE ? ESCAPE '\\'",
                            ["%{$escaped}%"],
                        );
                    }
                })
                ->limit(self::PER_RESOURCE_LIMIT)
                ->get()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param class-string $resourceClass
     */
    private function buildCommand(string $resourceClass, Model $record, string $panelPath): ?Command
    {
        try {
            $slug = $resourceClass::getSlug();
            $title = $resourceClass::globalSearchResultTitle($record);
        } catch (Throwable) {
            return null;
        }

        $icon = null;

        try {
            $icon = $resourceClass::getNavigationIcon();
        } catch (Throwable) {
            $icon = null;
        }

        return new Command(
            id: 'record:'.$slug.':'.$record->getKey(),
            label: $title,
            url: $panelPath.'/'.$slug.'/'.$record->getKey().'/edit',
            description: null,
            category: (string) __('arqel::palette.category.records'),
            icon: $icon,
            rankScore: self::RECORD_RANK_SCORE,
        );
    }

    /**
     * Same resolution as {@see NavigationCommandProvider::resolvePanelPath()}.
     */
    private function resolvePanelPath(): string
    {
        $panel = app(PanelRegistry::class)->getCurrent();
        $configPath = config('arqel.path', 'admin');
        $rawPath = $panel?->getPath() ?? (is_string($configPath) ? $configPath : 'admin');

        return '/'.trim($rawPath, '/');
    }
}
