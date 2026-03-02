<?php

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\TransactionResource;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
