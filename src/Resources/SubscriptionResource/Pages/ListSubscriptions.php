<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\SubscriptionResource\Pages;

use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\SubscriptionResource;
use OfficeGuy\LaravelSumitGateway\Jobs\ProcessRecurringPaymentsJob;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('process_due')
                ->label('Process Due Subscriptions')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Process Due Subscriptions')
                ->modalDescription('This will process all subscriptions that are due for charging. Continue?')
                ->action(function (): void {
                    dispatch(new ProcessRecurringPaymentsJob);

                    \Filament\Notifications\Notification::make()
                        ->title('Processing started')
                        ->body('Due subscriptions are being processed in the background.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
