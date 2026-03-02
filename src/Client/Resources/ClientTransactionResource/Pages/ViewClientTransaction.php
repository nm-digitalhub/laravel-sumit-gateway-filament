<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientTransactionResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientTransactionResource;

class ViewClientTransaction extends ViewRecord
{
    protected static string $resource = ClientTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
