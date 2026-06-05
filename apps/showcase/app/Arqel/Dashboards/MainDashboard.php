<?php

declare(strict_types=1);

namespace App\Arqel\Dashboards;

use App\Models\Author;
use App\Models\Post;
use App\Models\Ticket;
use Arqel\Widgets\ChartWidget;
use Arqel\Widgets\Dashboard;
use Arqel\Widgets\StatWidget;
use Arqel\Widgets\TableWidget;

/**
 * The showcase panel's landing dashboard.
 *
 * Exercises every widget primitive shipped by `arqel-dev/widgets`:
 * three KPI StatWidgets, a ChartWidget (posts grouped by status) and
 * a TableWidget listing the most recent posts. All data is pulled
 * inside Closures so it resolves lazily at serialisation time —
 * `Post` is read with `withoutGlobalScopes()` so the dashboard counts
 * span every tenant rather than the (absent) current one.
 */
final class MainDashboard
{
    public static function make(): Dashboard
    {
        return Dashboard::make('main', 'Overview', '/admin')
            ->heading('Overview')
            ->description('Showcase metrics across every tenant.')
            ->columns(['sm' => 1, 'md' => 2, 'lg' => 3])
            ->widgets([
                StatWidget::make('total_posts')
                    ->heading('Posts')
                    ->value(fn (): int => Post::withoutGlobalScopes()->count())
                    ->icon('file-text')
                    ->url('/admin/posts'),

                StatWidget::make('total_authors')
                    ->heading('Authors')
                    ->value(fn (): int => Author::count())
                    ->icon('users'),

                StatWidget::make('open_tickets')
                    ->heading('Open Tickets')
                    ->value(fn (): int => Ticket::where('status', 'open')->count())
                    ->icon('inbox'),

                ChartWidget::make('posts_by_status')
                    ->heading('Posts by status')
                    ->chartType(ChartWidget::CHART_BAR)
                    ->chartData(fn (): array => [
                        'labels' => ['draft', 'published', 'archived'],
                        'datasets' => [[
                            'label' => 'Posts',
                            'data' => [
                                Post::withoutGlobalScopes()->where('status', 'draft')->count(),
                                Post::withoutGlobalScopes()->where('status', 'published')->count(),
                                Post::withoutGlobalScopes()->where('status', 'archived')->count(),
                            ],
                        ]],
                    ]),

                TableWidget::make('recent_posts')
                    ->heading('Recent posts')
                    ->query(fn () => Post::withoutGlobalScopes()->latest())
                    ->limit(5)
                    ->seeAllUrl('/admin/posts'),
            ]);
    }
}
