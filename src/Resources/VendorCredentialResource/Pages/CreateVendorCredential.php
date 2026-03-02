<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\VendorCredentialResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\VendorCredentialResource;

class CreateVendorCredential extends CreateRecord
{
    protected static string $resource = VendorCredentialResource::class;
}
