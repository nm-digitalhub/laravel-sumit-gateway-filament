<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSubscriptionResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Cache;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSubscriptionResource;
use OfficeGuy\LaravelSumitGateway\Jobs\SyncDocumentsJob;
use OfficeGuy\LaravelSumitGateway\Services\SubscriptionService;

class ListClientSubscriptions extends ListRecords
{
    protected static string $resource = ClientSubscriptionResource::class;

    /**
     * Auto-sync subscriptions and documents when page loads
     *
     * Runs only once per hour per user to avoid excessive API calls
     */
    public function mount(): void
    {
        parent::mount();

        $user = auth()->user();

        if (! $user) {
            return;
        }

        // Cache key unique to this user
        $cacheKey = "subscriptions_synced_user_{$user->id}";

        // Check if already synced in the last hour
        if (Cache::has($cacheKey)) {
            return; // Skip sync - already done recently
        }

        try {
            // Sync subscriptions first (including inactive ones)
            $subscriptionCount = SubscriptionService::syncFromSumit($user, true);

            // Sync documents for user's subscriptions (last 30 days)
            // Run in background to avoid blocking page load
            SyncDocumentsJob::dispatch($user->id, 30, false);

            // Mark as synced for 1 hour
            Cache::put($cacheKey, true, now()->addHour());

            // Show success notification (only if new subscriptions were synced)
            if ($subscriptionCount > 0) {
                Notification::make()
                    ->title(__('Subscriptions updated'))
                    ->body(__(':count subscriptions synced from SUMIT', ['count' => $subscriptionCount]))
                    ->success()
                    ->send();
            }
        } catch (\Throwable $e) {
            // Log error but don't show to user (fail silently)
            \Log::error('Failed to auto-sync subscriptions on page load', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            // No create action - subscriptions are created via payment flow
        ];
    }
}
