<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource;

class ViewClientPaymentMethod extends ViewRecord
{
    protected static string $resource = ClientPaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('set_default')
                ->label('Set as Default')
                ->icon('heroicon-o-star')
                ->visible(fn ($record): bool => ! $record->is_default && ! $record->isExpired())
                ->requiresConfirmation()
                ->action(function ($record): void {
                    $record->setAsDefault();
                    Notification::make()
                        ->title('Payment method set as default')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make()
                ->modalHeading('Delete Payment Method')
                ->modalDescription('Are you sure you want to delete this saved payment method? This action cannot be undone.'),
        ];
    }
}
