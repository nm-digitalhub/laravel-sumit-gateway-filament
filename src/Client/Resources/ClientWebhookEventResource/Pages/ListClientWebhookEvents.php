<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientWebhookEventResource\Pages;

use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientWebhookEventResource;

class ListClientWebhookEvents extends ListRecords
{
    protected static string $resource = ClientWebhookEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
