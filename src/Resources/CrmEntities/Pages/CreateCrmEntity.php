<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Pages;

use Filament\Resources\Pages\CreateRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\CrmEntityResource;

class CreateCrmEntity extends CreateRecord
{
    protected static string $resource = CrmEntityResource::class;

    /**
     * Handle data mutation before creating the record.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Extract custom fields
        $customFields = [];
        $standardData = $data;

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'custom_field_')) {
                $customFields[$key] = $value;
                unset($standardData[$key]);
            }
        }

        // Store custom fields for later use in afterCreate
        $this->customFields = $customFields;

        return $standardData;
    }

    /**
     * Handle actions after creating the record.
     */
    protected function afterCreate(): void
    {
        // Save custom fields using the model's setCustomField method
        foreach ($this->customFields as $key => $value) {
            // Extract field ID from key: custom_field_123 -> 123
            $fieldId = (int) str_replace('custom_field_', '', $key);

            // Get the field to find its name
            $field = \OfficeGuy\LaravelSumitGateway\Models\CrmFolderField::find($fieldId);

            if ($field) {
                $this->record->setCustomField($field->field_name, $value);
            }
        }

        // Auto-push to SUMIT on create (best-effort, non-blocking)
        try {
            $this->record->syncToSumit();
        } catch (\Throwable) {
            // Swallow errors; manual sync action is available if needed.
        }
    }

    protected array $customFields = [];
}
