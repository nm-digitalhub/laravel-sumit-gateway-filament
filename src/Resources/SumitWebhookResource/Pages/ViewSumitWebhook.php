<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\SumitWebhookResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\SumitWebhookResource;

class ViewSumitWebhook extends ViewRecord
{
    protected static string $resource = SumitWebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('process')
                ->label('Process')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->record->isPending())
                ->requiresConfirmation()
                ->action(function (): void {
                    event(new \OfficeGuy\LaravelSumitGateway\Events\SumitWebhookReceived($this->record));

                    Notification::make()
                        ->title('Webhook dispatched for processing')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('mark_processed')
                ->label('Mark Processed')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->record->isPending())
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->markAsProcessed('Manually marked as processed');

                    Notification::make()
                        ->title('Webhook marked as processed')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'processed_at', 'processing_notes']);
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
