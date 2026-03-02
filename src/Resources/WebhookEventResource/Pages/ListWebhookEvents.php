<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\WebhookEventResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\WebhookEventResource;
use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;

class ListWebhookEvents extends ListRecords
{
    protected static string $resource = WebhookEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('retry_all_failed')
                ->label('Retry All Failed')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    $events = WebhookEvent::where('status', 'failed')
                        ->orWhere(function ($query): void {
                            $query->where('status', 'retrying')
                                ->where('next_retry_at', '<=', now());
                        })
                        ->get();

                    foreach ($events as $event) {
                        if ($event->canRetry()) {
                            $event->scheduleRetry(1);
                        }
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Retry scheduled for ' . $events->count() . ' events')
                        ->success()
                        ->send();
                })
                ->visible(fn () => WebhookEvent::where('status', 'failed')->exists()),
            Actions\Action::make('clear_sent')
                ->label('Clear Sent Events')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('This will permanently delete all successfully sent webhook events older than 7 days.')
                ->action(function (): void {
                    $deleted = WebhookEvent::where('status', 'sent')
                        ->where('created_at', '<', now()->subDays(7))
                        ->delete();

                    \Filament\Notifications\Notification::make()
                        ->title("Deleted {$deleted} old events")
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WebhookEventResource\Widgets\WebhookStatsOverview::class,
        ];
    }
}
