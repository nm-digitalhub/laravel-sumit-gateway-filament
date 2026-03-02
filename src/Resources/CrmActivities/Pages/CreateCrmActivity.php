<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Pages;

use Filament\Resources\Pages\CreateRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\CrmActivityResource;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;

class CreateCrmActivity extends CreateRecord
{
    protected static string $resource = CrmActivityResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set user_id to current user if not specified
        if (empty($data['user_id'])) {
            $data['user_id'] = auth()->id();
        }

        // Set default status if not specified
        if (empty($data['status'])) {
            $data['status'] = 'planned';
        }

        // Set default priority if not specified
        if (empty($data['priority'])) {
            $data['priority'] = 'normal';
        }

        return $data;
    }

    /**
     * After create hook – push remark to SUMIT (Accounting Customers) when possible.
     */

    /**
     * After create hook – push remark to SUMIT (Accounting Customers) when possible.
     */
    protected function afterCreate(): void
    {
        // Ensure client_id is set from entity (if not already)
        if (empty($this->record->client_id) && $this->record->entity?->client_id) {
            $this->record->updateQuietly(['client_id' => $this->record->entity->client_id]);
        }

        $activity = $this->record;
        $entity = $activity->entity; // relation on CrmActivity

        $sumitCustomerId = $entity?->sumit_entity_id;

        if (! $sumitCustomerId) {
            return;
        }

        $content = trim(($activity->subject ? $activity->subject . ': ' : '') . ($activity->description ?? ''));

        $payload = [
            'Credentials' => PaymentService::getCredentials(),
            'CustomerID' => $sumitCustomerId,
            'Content' => $content !== '' ? $content : 'Activity created in NM DigitalHub',
            'Username' => optional(auth()->user())->name,
        ];

        try {
            OfficeGuyApi::post(
                $payload,
                '/accounting/customers/createremark/',
                config('officeguy.environment', 'www'),
                false
            );
        } catch (\Throwable) {
            // Swallow error to avoid blocking UI; could log if needed.
        }
    }
}
