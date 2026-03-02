<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;

class ViewToken extends ViewRecord
{
    protected static string $resource = TokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('set_default')
                ->label('Set as Default')
                ->icon('heroicon-o-star')
                ->visible(fn ($record): bool => ! $record->is_default)
                ->requiresConfirmation()
                ->action(function ($record): void {
                    // Get owner's SUMIT customer ID
                    $owner = $record->owner;
                    $client = $owner?->client ?? $owner;
                    $sumitCustomerId = $client?->sumit_customer_id ?? null;

                    if ($sumitCustomerId) {
                        // Sync with SUMIT first
                        $result = PaymentService::setPaymentMethodForCustomer(
                            $sumitCustomerId,
                            $record->token,
                            $record->metadata ?? []
                        );

                        if (! $result['success']) {
                            Notification::make()
                                ->title('Failed to update SUMIT')
                                ->body($result['error'] ?? 'Unknown error')
                                ->danger()
                                ->send();

                            return;
                        }
                    }

                    // Update local database
                    $record->setAsDefault();
                    Notification::make()
                        ->title('Token set as default')
                        ->body($sumitCustomerId ? 'Updated in SUMIT and local database' : 'Updated in local database only')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
