<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSumitWebhookResource\Pages;

use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSumitWebhookResource;

class ListClientSumitWebhooks extends ListRecords
{
    protected static string $resource = ClientSumitWebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
