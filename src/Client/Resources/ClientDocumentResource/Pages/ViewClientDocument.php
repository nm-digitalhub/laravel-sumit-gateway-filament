<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientDocumentResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientDocumentResource;

class ViewClientDocument extends ViewRecord
{
    protected static string $resource = ClientDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
