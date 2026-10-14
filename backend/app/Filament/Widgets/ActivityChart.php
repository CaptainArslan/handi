<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ActivityChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Sign-ups and meals logged';

    protected ?string $description = 'Last 30 days';

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 2];

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn (int $ago) => now()->subDays($ago)->toDateString());

        return [
            'datasets' => [
                [
                    'label' => 'Meals logged',
                    'data' => $this->series($days, 'meal_logs', 'date', fn ($q) => $q->whereNull('deleted_at')),
                    'borderColor' => '#0F5C4D',
                    'backgroundColor' => 'rgba(15, 92, 77, 0.12)',
                    'fill' => true,
                    'cubicInterpolationMode' => 'monotone',
                    'pointRadius' => 2,
                ],
                [
                    'label' => 'New users',
                    'data' => $this->series($days, 'users', 'created_at', fn ($q) => $q->where('is_admin', false)),
                    'borderColor' => '#F2A93B',
                    'backgroundColor' => 'rgba(242, 169, 59, 0.15)',
                    'fill' => true,
                    'cubicInterpolationMode' => 'monotone',
                    'pointRadius' => 2,
                ],
            ],
            'labels' => $days->map(fn (string $d) => date('j M', strtotime($d)))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0, 'font' => ['family' => 'Poppins', 'size' => 13]]],
                'x' => ['ticks' => ['font' => ['family' => 'Poppins', 'size' => 12], 'maxRotation' => 0, 'autoSkipPadding' => 14]],
            ],
            'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['font' => ['family' => 'Poppins', 'size' => 13], 'usePointStyle' => true, 'padding' => 16]]],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function series($days, string $table, string $column, ?callable $scope = null): array
    {
        $q = DB::table($table)->where($column, '>=', now()->subDays(29)->startOfDay());
        if ($scope) {
            $scope($q);
        }
        $counts = $q->selectRaw("DATE({$column}) as d, COUNT(*) as n")->groupBy('d')->pluck('n', 'd');

        return $days->map(fn (string $d) => (int) ($counts[$d] ?? 0))->all();
    }
}
