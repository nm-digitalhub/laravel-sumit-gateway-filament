<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Pages;

use Filament\Resources\Pages\CreateRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\CrmFolderResource;

class CreateCrmFolder extends CreateRecord
{
    protected static string $resource = CrmFolderResource::class;
}
