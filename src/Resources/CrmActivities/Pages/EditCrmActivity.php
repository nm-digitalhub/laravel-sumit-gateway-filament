<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\CrmActivityResource;

class EditCrmActivity extends EditRecord
{
    protected static string $resource = CrmActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['crm_entity_id'])) {
            $entity = \OfficeGuy\LaravelSumitGateway\Models\CrmEntity::find($data['crm_entity_id']);
            if ($entity && $entity->client_id) {
                $data['client_id'] = $entity->client_id;
            }
        }

        return $data;
    }
}
