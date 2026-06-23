<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Arqel\Dashboards\MainDashboard;
use Arqel\Widgets\ChartWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MainDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function chartLabels(): array
    {
        $dashboard = MainDashboard::make();

        foreach ($dashboard->getWidgets() as $widget) {
            if ($widget instanceof ChartWidget) {
                return $widget->toArray()['data']['chartData']['labels'] ?? [];
            }
        }

        return [];
    }

    public function test_chart_labels_honour_the_active_locale(): void
    {
        // Freeze on a date whose month abbreviation differs by locale.
        Carbon::setTestNow(Carbon::create(2026, 6, 23));

        $previous = app()->getLocale();
        app()->setLocale('pt_BR');
        Carbon::setLocale('pt_BR');

        try {
            $labels = $this->chartLabels();
        } finally {
            app()->setLocale($previous);
            Carbon::setLocale($previous);
            Carbon::setTestNow();
        }

        $this->assertNotEmpty($labels);

        // Under pt_BR the month token must be the translated (lowercase) form,
        // never the hardcoded English "Jun" that ->format('M j') would emit.
        $joined = implode(' ', $labels);
        $this->assertStringContainsString('jun', $joined);
        $this->assertStringNotContainsString('Jun', $joined);
    }
}
