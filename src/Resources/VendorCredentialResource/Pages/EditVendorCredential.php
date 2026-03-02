<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\VendorCredentialResource\Pages;

use Filament\Resources\Pages\EditRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\VendorCredentialResource;

class EditVendorCredential extends EditRecord
{
    protected static string $resource = VendorCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
        ];
    }
}
