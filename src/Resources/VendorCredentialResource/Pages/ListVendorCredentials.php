<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\VendorCredentialResource\Pages;

use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\VendorCredentialResource;

class ListVendorCredentials extends ListRecords
{
    protected static string $resource = VendorCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
