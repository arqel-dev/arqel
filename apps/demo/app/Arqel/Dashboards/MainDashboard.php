<?php

declare(strict_types=1);

namespace App\Arqel\Dashboards;

use App\Models\Post;
use Arqel\Table\Columns\BadgeColumn;
use Arqel\Table\Columns\DateColumn;
use Arqel\Table\Columns\TextColumn;
use Arqel\Widgets\ChartWidget;
use Arqel\Widgets\Dashboard;
use Arqel\Widgets\StatWidget;
use Arqel\Widgets\TableWidget;
use Illuminate\Support\Carbon;

/**
 * Painel inicial do admin demo.
 *
 * Combina os três widget types canônicos (`stat`, `chart`, `table`)
 * sobre o mesmo modelo `Post` para exercitar end-to-end a stack
 * `arqel-dev/widgets` + `@arqel-dev/ui/widgets`. É também o dashboard
 * que a suíte E2E de Playwright (`10-dashboard.spec.ts`) abre via
 * `/admin/dashboards/main`.
 */
final class MainDashboard
{
    public static function make(): Dashboard
    {
        return Dashboard::make('main', 'Overview', '/admin/dashboards/main')
            ->heading('Overview')
            ->description('Resumo da actividade do blog.')
            ->columns(['sm' => 1, 'md' => 2, 'lg' => 3])
            ->widgets([
                StatWidget::make('total_posts')
                    ->heading('Total posts')
                    ->description('Total acumulado no banco')
                    ->color(StatWidget::COLOR_PRIMARY)
                    ->value(fn (): int => Post::query()->count()),

                ChartWidget::make('posts_per_day')
                    ->heading('Posts per day')
                    ->description('Distribuição de criação dos últimos 7 dias')
                    ->chartType(ChartWidget::CHART_BAR)
                    ->columnSpan(2)
                    ->chartData(fn (): array => self::postsPerDay()),

                TableWidget::make('recent_posts')
                    ->heading('Recent posts')
                    ->description('Os 5 mais recentes')
                    ->columnSpan(3)
                    ->limit(5)
                    ->columns([
                        TextColumn::make('title')->limit(60),
                        BadgeColumn::make('status')->colors([
                            'draft' => 'gray',
                            'published' => 'green',
                            'archived' => 'yellow',
                        ]),
                        DateColumn::make('created_at')->dateTime('d/m/Y H:i'),
                    ])
                    ->query(fn () => Post::query()->latest('created_at')),
            ]);
    }

    /**
     * @return array{labels: list<string>, datasets: list<array{label: string, data: list<int>}>}
     */
    private static function postsPerDay(): array
    {
        $labels = [];
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->startOfDay();
            $labels[] = $day->format('M j');
            $data[] = (int) Post::query()
                ->whereBetween('created_at', [$day, $day->copy()->endOfDay()])
                ->count();
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Posts', 'data' => $data],
            ],
        ];
    }
}
