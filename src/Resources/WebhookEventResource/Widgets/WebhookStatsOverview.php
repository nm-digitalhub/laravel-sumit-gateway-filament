<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\WebhookEventResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;

class WebhookStatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $totalToday = WebhookEvent::whereDate('created_at', today())->count();
        $sentToday = WebhookEvent::whereDate('created_at', today())
            ->where('status', 'sent')
            ->count();
        $failedCount = WebhookEvent::where('status', 'failed')->count();
        $pendingRetry = WebhookEvent::where('status', 'retrying')
            ->where('next_retry_at', '<=', now())
            ->count();
        $successRate = $totalToday > 0
            ? round(($sentToday / $totalToday) * 100, 1)
            : 100;

        // Get event type breakdown for today
        $eventBreakdown = WebhookEvent::whereDate('created_at', today())
            ->select('event_type', DB::raw('count(*) as count'))
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();

        $breakdownText = collect($eventBreakdown)
            ->map(fn ($count, $type): string => ucfirst(str_replace('_', ' ', $type)) . ": {$count}")
            ->take(3)
            ->implode(' | ');

        return [
            Stat::make('Events Today', $totalToday)
                ->description($breakdownText ?: 'No events today')
                ->descriptionIcon('heroicon-o-signal')
                ->color('primary')
                ->chart($this->getChartData('total')),
            Stat::make('Success Rate', $successRate . '%')
                ->description("Sent: {$sentToday} of {$totalToday}")
                ->descriptionIcon('heroicon-o-check-circle')
                ->color($successRate >= 90 ? 'success' : ($successRate >= 70 ? 'warning' : 'danger'))
                ->chart($this->getChartData('success')),
            Stat::make('Failed', $failedCount)
                ->description($pendingRetry > 0 ? "{$pendingRetry} pending retry" : 'No pending retries')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color($failedCount > 0 ? 'danger' : 'success')
                ->chart($this->getChartData('failed')),
            Stat::make('Avg Response Time', $this->getAverageResponseTime() . 'ms')
                ->description('Last 100 requests')
                ->descriptionIcon('heroicon-o-clock')
                ->color('gray'),
        ];
    }

    protected function getChartData(string $type): array
    {
        $days = collect(range(6, 0))->map(function ($daysAgo) use ($type) {
            $date = now()->subDays($daysAgo)->toDateString();

            $query = WebhookEvent::whereDate('created_at', $date);

            return match ($type) {
                'total' => $query->count(),
                'success' => $query->where('status', 'sent')->count(),
                'failed' => $query->where('status', 'failed')->count(),
                default => 0,
            };
        });

        return $days->toArray();
    }

    protected function getAverageResponseTime(): string
    {
        // This is a placeholder - in a real implementation,
        // you would track response times in the webhook_events table
        $sentEvents = WebhookEvent::where('status', 'sent')
            ->whereNotNull('sent_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        if ($sentEvents->isEmpty()) {
            return 'N/A';
        }

        // Estimate based on sent_at - created_at difference (rough approximation)
        $avgMs = $sentEvents->avg(function ($event) {
            if ($event->sent_at && $event->created_at) {
                return $event->sent_at->diffInMilliseconds($event->created_at);
            }

            return 0;
        });

        return number_format($avgMs, 0);
    }
}
