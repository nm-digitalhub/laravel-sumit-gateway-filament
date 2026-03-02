<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Pages;

use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\CrmActivityResource;

class ViewCrmActivity extends ViewRecord
{
    protected static string $resource = CrmActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No edit or delete actions - activities are read-only
        ];
    }
}
