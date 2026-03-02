<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament;

use BezhanSalleh\PluginEssentials\Concerns\Plugin\HasLabels;
use BezhanSalleh\PluginEssentials\Concerns\Plugin\HasNavigation;
use BezhanSalleh\PluginEssentials\Concerns\Plugin\WithMultipleResourceSupport;
use Filament\Contracts\Plugin;
use Filament\Panel;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientDocumentResource;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSubscriptionResource;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSumitWebhookResource;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientTransactionResource;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientWebhookEventResource;
use OfficeGuy\LaravelSumitGateway\Filament\Clusters\SumitClient;

/**
 * OfficeGuy SUMIT Gateway Filament Plugin (Client Panel)
 *
 * Registers all SUMIT Gateway Client Panel Resources with Filament.
 * Uses filament-plugin-essentials for centralized configuration.
 *
 * @see https://filamentphp.com/docs/4.x/plugins/panel-plugins
 */
class OfficeGuyClientPlugin implements Plugin
{
    use HasLabels;
    use HasNavigation;
    use WithMultipleResourceSupport;

    public function getId(): string
    {
        return 'officeguy-sumit-gateway-client';
    }

    public function register(Panel $panel): void
    {
        // Client Panel uses auto-discovery via ClientPanelProvider
        // No need to register resources here
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
     * OfficeGuyClientPlugin::get()->someMethod()
     */
    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    /**
     * Plugin defaults for Client Panel Resources.
     *
     * 3-tier priority system:
     * 1. User Overrides (highest) - Set via fluent API in Panel provider
     * 2. Plugin Defaults (these) - Defined here
     * 3. Filament Defaults (lowest) - Built-in Filament defaults
     *
     * Note: Client resources have dynamic badge methods that override these defaults.
     */
    protected function getPluginDefaults(): array
    {
        return [
            // Global defaults (apply to ALL Client resources)
            'navigationCluster' => SumitClient::class,
            'titleCaseModelLabel' => false, // Keep Hebrew as-is

            // Resource-specific defaults
            'resources' => [
                // ClientPaymentMethodResource
                ClientPaymentMethodResource::class => [
                    'navigationLabel' => 'אמצעי תשלום',
                    'navigationIcon' => 'heroicon-o-credit-card',
                    'navigationSort' => 1,
                    'modelLabel' => 'אמצעי תשלום',
                    'pluralModelLabel' => 'אמצעי תשלום',
                    'recordTitleAttribute' => 'last_four',
                ],

                // ClientTransactionResource
                ClientTransactionResource::class => [
                    'navigationLabel' => 'טרנזאקציות',
                    'navigationIcon' => 'heroicon-o-receipt',
                    'navigationSort' => 2,
                    'modelLabel' => 'טרנזאקציה',
                    'pluralModelLabel' => 'טרנזאקציות',
                    'recordTitleAttribute' => 'id',
                ],

                // ClientDocumentResource
                ClientDocumentResource::class => [
                    'navigationLabel' => 'מסמכים',
                    'navigationIcon' => 'heroicon-o-document-text',
                    'navigationSort' => 3,
                    'modelLabel' => 'מסמך',
                    'pluralModelLabel' => 'מסמכים',
                    'recordTitleAttribute' => 'id',
                ],

                // ClientSubscriptionResource
                ClientSubscriptionResource::class => [
                    'navigationLabel' => 'מנויים',
                    'navigationIcon' => 'heroicon-o-arrows-rotate',
                    'navigationSort' => 4,
                    'modelLabel' => 'מנוי',
                    'pluralModelLabel' => 'מנויים',
                    'recordTitleAttribute' => 'id',
                ],

                // ClientWebhookEventResource
                ClientWebhookEventResource::class => [
                    'navigationLabel' => 'אירועי Webhook',
                    'navigationIcon' => 'heroicon-o-bolt',
                    'navigationSort' => 5,
                    'modelLabel' => 'אירוע Webhook',
                    'pluralModelLabel' => 'אירועי Webhook',
                    'recordTitleAttribute' => 'id',
                ],

                // ClientSumitWebhookResource
                ClientSumitWebhookResource::class => [
                    'navigationLabel' => 'SUMIT Webhooks',
                    'navigationIcon' => 'heroicon-o-signal',
                    'navigationSort' => 6,
                    'modelLabel' => 'SUMIT Webhook',
                    'pluralModelLabel' => 'SUMIT Webhooks',
                    'recordTitleAttribute' => 'id',
                ],
            ],
        ];
    }
}
