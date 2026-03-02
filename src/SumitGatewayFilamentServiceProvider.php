<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament;

use Illuminate\Support\ServiceProvider;

/**
 * Filament UI adapter for OfficeGuy SUMIT Gateway.
 *
 * Registers Livewire components and Filament clusters when Filament is present.
 * Require this package only when using the Filament admin/client panels.
 */
class SumitGatewayFilamentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->injectFilamentRouteConfig();
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'officeguy');
        $this->registerLivewireComponents();
        $this->registerFilamentClusters();
    }

    /**
     * Inject Filament route names into core config so core views use Filament URLs.
     */
    protected function injectFilamentRouteConfig(): void
    {
        config([
            'officeguy.routes.client_login_route' => 'filament.client.auth.login',
            'officeguy.notification_routes.transaction_view' => 'filament.admin.sumit-gateway.resources.transactions.view',
            'officeguy.notification_routes.document_view' => 'filament.admin.sumit-gateway.resources.documents.view',
            'officeguy.notification_routes.subscription_view' => 'filament.admin.sumit-gateway.resources.subscriptions.view',
            'officeguy.notification_routes.token_view' => 'filament.admin.sumit-gateway.resources.tokens.view',
            'officeguy.notification_routes.ticket_create' => 'filament.client.resources.tickets.create',
            'officeguy.notification_routes.profile_page_upgraded' => 'filament.client.pages.profile-page-upgraded',
            'officeguy.notification_routes.clients_index' => 'filament.admin.resources.clients.index',
            'officeguy.notification_routes.order_view' => 'filament.admin.resources.orders.view',
        ]);
    }

    protected function registerLivewireComponents(): void
    {
        if (!class_exists(\Livewire\Livewire::class)) {
            return;
        }

        \Livewire\Livewire::component(
            'office-guy.laravel-sumit-gateway.filament.widgets.payable-mappings-table-widget',
            \OfficeGuy\LaravelSumitGateway\Filament\Widgets\PayableMappingsTableWidget::class
        );
    }

    protected function registerFilamentClusters(): void
    {
        if (!class_exists(\Filament\Facades\Filament::class)) {
            return;
        }

        \Filament\Facades\Filament::serving(function () {
            try {
                $adminPanel = \Filament\Facades\Filament::getPanel('admin');
                $adminPanel->clusters([
                    \OfficeGuy\LaravelSumitGateway\Filament\Clusters\SumitGateway::class,
                ]);
            } catch (\Throwable $e) {
                // Admin panel may not be registered
            }

            try {
                $clientPanel = \Filament\Facades\Filament::getPanel('client');
                $clientPanel->clusters([
                    \OfficeGuy\LaravelSumitGateway\Filament\Clusters\SumitClient::class,
                ]);
            } catch (\Throwable $e) {
                // Client panel may not be registered
            }
        });
    }
}
