<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament;

use BezhanSalleh\PluginEssentials\Concerns\Plugin\HasGlobalSearch;
use BezhanSalleh\PluginEssentials\Concerns\Plugin\HasLabels;
use BezhanSalleh\PluginEssentials\Concerns\Plugin\HasNavigation;
use BezhanSalleh\PluginEssentials\Concerns\Plugin\WithMultipleResourceSupport;
use Filament\Contracts\Plugin;
use Filament\Panel;
use OfficeGuy\LaravelSumitGateway\Filament\Clusters\SumitGateway;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\CrmActivityResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\CrmEntityResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\CrmFolderResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\DocumentResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\SubscriptionResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\SumitWebhookResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\TransactionResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\VendorCredentialResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\WebhookEventResource;

/**
 * OfficeGuy SUMIT Gateway Filament Plugin (Admin Panel)
 *
 * Registers all SUMIT Gateway Admin Resources, Pages, and Clusters with Filament panels.
 * Uses filament-plugin-essentials for centralized configuration.
 *
 * @see https://filamentphp.com/docs/4.x/plugins/panel-plugins
 */
class OfficeGuyPlugin implements Plugin
{
    use HasGlobalSearch;
    use HasLabels;
    use HasNavigation;
    use WithMultipleResourceSupport;

    public function getId(): string
    {
        return 'officeguy-sumit-gateway';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->discoverResources(
                in: __DIR__ . '/Resources',
                for: 'OfficeGuy\\LaravelSumitGateway\\Filament\\Resources'
            )
            ->discoverPages(
                in: __DIR__ . '/Pages',
                for: 'OfficeGuy\\LaravelSumitGateway\\Filament\\Pages'
            )
            ->discoverClusters(
                in: __DIR__ . '/Clusters',
                for: 'OfficeGuy\\LaravelSumitGateway\\Filament\\Clusters'
            );
    }

    public function boot(Panel $panel): void
    {
        // Logic to run when the panel is booted
    }

    /**
     * Fluent interface for instantiating the plugin.
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Get the plugin instance from Filament.
     *
     * Allows accessing plugin configuration:
     * OfficeGuyPlugin::get()->someMethod()
     */
    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    /**
     * Plugin defaults for Admin Panel Resources.
     *
     * 3-tier priority system:
     * 1. User Overrides (highest) - Set via fluent API in Panel provider
     * 2. Plugin Defaults (these) - Defined here
     * 3. Filament Defaults (lowest) - Built-in Filament defaults
     */
    protected function getPluginDefaults(): array
    {
        return [
            // Global defaults (apply to ALL Admin resources)
            'navigationCluster' => SumitGateway::class,
            'globallySearchable' => true,
            'globalSearchResultsLimit' => 20,
            'titleCaseModelLabel' => false, // Keep Hebrew/English as-is

            // Resource-specific defaults
            'resources' => [
                // TransactionResource
                TransactionResource::class => [
                    'navigationLabel' => 'טרנזאקציות',
                    'navigationIcon' => 'heroicon-o-credit-card',
                    'navigationSort' => 1,
                    'modelLabel' => 'Transaction',
                    'pluralModelLabel' => 'Transactions',
                    'recordTitleAttribute' => 'id',
                ],

                // TokenResource
                TokenResource::class => [
                    'navigationLabel' => 'Payment Tokens',
                    'navigationIcon' => 'heroicon-o-credit-card',
                    'navigationSort' => 2,
                    'modelLabel' => 'Token',
                    'pluralModelLabel' => 'Tokens',
                    'recordTitleAttribute' => 'token',
                ],

                // DocumentResource
                DocumentResource::class => [
                    'navigationLabel' => 'Documents',
                    'navigationIcon' => 'heroicon-o-document',
                    'navigationSort' => 3,
                    'modelLabel' => 'Document',
                    'pluralModelLabel' => 'Documents',
                    'recordTitleAttribute' => 'id',
                ],

                // SubscriptionResource
                SubscriptionResource::class => [
                    'navigationLabel' => 'Subscriptions',
                    'navigationIcon' => 'heroicon-o-arrows-rotate',
                    'navigationSort' => 4,
                    'modelLabel' => 'Subscription',
                    'pluralModelLabel' => 'Subscriptions',
                    'recordTitleAttribute' => 'id',
                ],

                // VendorCredentialResource
                VendorCredentialResource::class => [
                    'navigationLabel' => 'Vendor Credentials',
                    'navigationIcon' => 'heroicon-o-key',
                    'navigationSort' => 5,
                    'modelLabel' => 'Vendor Credential',
                    'pluralModelLabel' => 'Vendor Credentials',
                    'recordTitleAttribute' => 'vendor_name',
                ],

                // WebhookEventResource
                WebhookEventResource::class => [
                    'navigationLabel' => 'Webhook Events',
                    'navigationIcon' => 'heroicon-o-bolt',
                    'navigationSort' => 6,
                    'modelLabel' => 'Webhook Event',
                    'pluralModelLabel' => 'Webhook Events',
                    'recordTitleAttribute' => 'id',
                ],

                // SumitWebhookResource
                SumitWebhookResource::class => [
                    'navigationLabel' => 'SUMIT Webhooks',
                    'navigationIcon' => 'heroicon-o-signal',
                    'navigationSort' => 7,
                    'modelLabel' => 'SUMIT Webhook',
                    'pluralModelLabel' => 'SUMIT Webhooks',
                    'recordTitleAttribute' => 'id',
                ],

                // CrmFolderResource
                CrmFolderResource::class => [
                    'navigationLabel' => 'CRM Folders',
                    'navigationIcon' => 'heroicon-o-folder',
                    'navigationSort' => 8,
                    'modelLabel' => 'CRM Folder',
                    'pluralModelLabel' => 'CRM Folders',
                    'recordTitleAttribute' => 'name',
                ],

                // CrmEntityResource
                CrmEntityResource::class => [
                    'navigationLabel' => 'CRM Entities',
                    'navigationIcon' => 'heroicon-o-circle-stack',
                    'navigationSort' => 9,
                    'modelLabel' => 'CRM Entity',
                    'pluralModelLabel' => 'CRM Entities',
                    'recordTitleAttribute' => 'name',
                ],

                // CrmActivityResource
                CrmActivityResource::class => [
                    'navigationLabel' => 'CRM Activities',
                    'navigationIcon' => 'heroicon-o-clock',
                    'navigationSort' => 10,
                    'modelLabel' => 'CRM Activity',
                    'pluralModelLabel' => 'CRM Activities',
                    'recordTitleAttribute' => 'id',
                ],
            ],
        ];
    }
}
