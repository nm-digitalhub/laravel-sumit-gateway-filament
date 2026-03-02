<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\CrmFolderResource;

class ListCrmFolders extends ListRecords
{
    protected static string $resource = CrmFolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_all_folders')
                ->label('Sync All Folders')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Sync All Folders from SUMIT')
                ->modalDescription('This will fetch all CRM folders from SUMIT and sync their schemas.')
                ->action(function () {
                    try {
                        // Run the artisan command
                        \Artisan::call('crm:sync-folders');

                        $output = \Artisan::output();

                        Notification::make()
                            ->title('Folders synced successfully')
                            ->body($output)
                            ->success()
                            ->send();

                        // Redirect to refresh the page
                        return redirect()->to(CrmFolderResource::getUrl('index'));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Sync failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
