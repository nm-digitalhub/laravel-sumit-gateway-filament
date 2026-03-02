<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource;

class EditToken extends EditRecord
{
    protected static string $resource = TokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_from_sumit')
                ->label('Sync from SUMIT')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->action(function (\OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken $record) {
                    $result = \OfficeGuy\LaravelSumitGateway\Services\TokenService::syncTokenFromSumit($record);

                    if ($result['success']) {
                        Notification::make()
                            ->title('Token synced successfully')
                            ->body('Token data updated from SUMIT. Page will reload.')
                            ->success()
                            ->send();

                        // Reload the page to show updated data
                        return redirect()->to(static::getResource()::getUrl('edit', ['record' => $record]));
                    }
                    Notification::make()
                        ->title('Sync failed')
                        ->body($result['error'] ?? 'Unknown error')
                        ->danger()
                        ->send();
                }),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Token updated successfully';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
