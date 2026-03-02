<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\SumitWebhookResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use OfficeGuy\LaravelSumitGateway\Models\SumitWebhook;

class SumitWebhookStatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $todayCount = SumitWebhook::whereDate('created_at', today())->count();
        $weekCount = SumitWebhook::where('created_at', '>=', now()->subDays(7))->count();
        $pendingCount = SumitWebhook::where('status', 'received')->count();
        $failedCount = SumitWebhook::where('status', 'failed')->count();

        // Calculate processing rate (last 7 days)
        $totalProcessed = SumitWebhook::where('created_at', '>=', now()->subDays(7))
            ->whereIn('status', ['processed', 'ignored'])
            ->count();
        $processingRate = $weekCount > 0 ? round(($totalProcessed / $weekCount) * 100, 1) : 0;

        // Get trend data for charts (last 7 days)
        $dailyStats = SumitWebhook::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total')
        )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->pluck('total')
            ->toArray();

        // Get event type distribution
        $eventTypeCounts = SumitWebhook::select('event_type', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('event_type')
            ->pluck('count')
            ->toArray();

        return [
            Stat::make('Webhooks Today', $todayCount)
                ->description('From SUMIT system')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->chart($dailyStats)
                ->color($todayCount > 0 ? 'primary' : 'gray'),

            Stat::make('Pending Processing', $pendingCount)
                ->description('Awaiting action')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingCount > 0 ? 'warning' : 'success'),

            Stat::make('Processing Rate', $processingRate . '%')
                ->description('Last 7 days')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->chart($eventTypeCounts)
                ->color($processingRate >= 90 ? 'success' : ($processingRate >= 70 ? 'warning' : 'danger')),

            Stat::make('Failed Webhooks', $failedCount)
                ->description('Need attention')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($failedCount > 0 ? 'danger' : 'success'),
        ];
    }
}
