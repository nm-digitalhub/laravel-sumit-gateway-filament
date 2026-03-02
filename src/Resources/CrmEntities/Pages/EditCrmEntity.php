<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\CrmEntityResource;

class EditCrmEntity extends EditRecord
{
    protected static string $resource = CrmEntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Handle data mutation before filling the form.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load custom fields from the entity
        $customFields = $this->record->customFields()->get();

        foreach ($customFields as $customField) {
            $fieldKey = "custom_field_{$customField->crm_folder_field_id}";
            $data[$fieldKey] = $customField->field_value;
        }

        return $data;
    }

    /**
     * Handle data mutation before saving the record.
     */
    protected function mutateFormDataBeforeSave(array $data): array
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

        // Store custom fields for later use in afterSave
        $this->customFields = $customFields;

        // If client_id not provided, try to match based on SUMIT ID and known fields
        if (empty($standardData['client_id']) && ! empty($standardData['sumit_entity_id'])) {
            $entityData = [
                'Customers_CompanyNumber' => [$standardData['vat_number'] ?? null],
                'Customers_EmailAddress' => [$standardData['email'] ?? $standardData['client_email'] ?? null],
                'Customers_Phone' => [$standardData['phone'] ?? $standardData['client_phone'] ?? $standardData['mobile_phone'] ?? null],
            ];

            $standardData['client_id'] = \OfficeGuy\LaravelSumitGateway\Services\CrmDataService::matchClientId(
                $entityData,
                (int) $standardData['sumit_entity_id']
            );
        }

        return $standardData;
    }

    /**
     * Handle actions after saving the record.
     */
    protected function afterSave(): void
    {
        // Update custom fields using the model's setCustomField method
        foreach ($this->customFields as $key => $value) {
            // Extract field ID from key: custom_field_123 -> 123
            $fieldId = (int) str_replace('custom_field_', '', $key);

            // Get the field to find its name
            $field = \OfficeGuy\LaravelSumitGateway\Models\CrmFolderField::find($fieldId);

            if ($field) {
                $this->record->setCustomField($field->field_name, $value);
            }
        }

        // Auto-push to SUMIT on save (create/update)
        try {
            $this->record->syncToSumit();
        } catch (\Throwable) {
            // Swallow errors to avoid blocking UI; sync action is available manually.
        }
    }

    protected array $customFields = [];
}
