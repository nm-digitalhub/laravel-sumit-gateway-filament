<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\WebhookEventResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\TransactionResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\WebhookEventResource;
use OfficeGuy\LaravelSumitGateway\Services\WebhookService;

class ViewWebhookEvent extends ViewRecord
{
    protected static string $resource = WebhookEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('retry')
                ->label('Retry Now')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn ($record) => $record->canRetry())
                ->requiresConfirmation()
                ->action(function (): void {
                    $record = $this->getRecord();
                    $webhookService = app(WebhookService::class);
                    $success = $webhookService->send($record->event_type, $record->payload ?? []);

                    if ($success) {
                        $record->markAsSent(200);
                        Notification::make()
                            ->title('Webhook resent successfully')
                            ->success()
                            ->send();
                    } else {
                        $record->scheduleRetry();
                        Notification::make()
                            ->title('Webhook retry failed')
                            ->body('Scheduled for automatic retry.')
                            ->warning()
                            ->send();
                    }

                    $this->refreshFormData(['status', 'retry_count', 'sent_at', 'next_retry_at']);
                }),
            Actions\Action::make('view_transaction')
                ->label('View Transaction')
                ->icon('heroicon-o-credit-card')
                ->color('primary')
                ->url(fn ($record): ?string => $record->transaction_id
                    ? WebhookEventResource::getUrl('view', ['record' => $record->transaction_id], resource: TransactionResource::class)
                    : null)
                ->visible(fn ($record): bool => $record->transaction_id !== null),
            Actions\Action::make('view_subscription')
                ->label('View Subscription')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('primary')
                ->url(fn ($record): ?string => $record->subscription_id
                    ? WebhookEventResource::getUrl('view', ['record' => $record->subscription_id], resource: \OfficeGuy\LaravelSumitGateway\Filament\Resources\SubscriptionResource::class)
                    : null)
                ->visible(fn ($record): bool => $record->subscription_id !== null),
            Actions\DeleteAction::make()
                ->visible(fn ($record): bool => $record->status !== 'pending'),
        ];
    }
}
