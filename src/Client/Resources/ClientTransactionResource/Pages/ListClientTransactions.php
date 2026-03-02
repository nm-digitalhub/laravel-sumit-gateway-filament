<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientTransactionResource\Pages;

use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientTransactionResource;

class ListClientTransactions extends ListRecords
{
    protected static string $resource = ClientTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
