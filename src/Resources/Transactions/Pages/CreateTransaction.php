<?php

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\Pages;

use Filament\Resources\Pages\CreateRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\TransactionResource;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;
}
