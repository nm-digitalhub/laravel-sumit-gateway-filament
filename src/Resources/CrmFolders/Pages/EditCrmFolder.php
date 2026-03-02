<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\CrmFolderResource;

class EditCrmFolder extends EditRecord
{
    protected static string $resource = CrmFolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
