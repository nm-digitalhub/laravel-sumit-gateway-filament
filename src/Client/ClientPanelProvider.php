<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client;

use Bytexr\QueueableBulkActions\QueueableBulkActionsPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('client')
            ->path('client')
            ->plugin(QueueableBulkActionsPlugin::make())
            ->login()
            ->colors([
                'primary' => '#0ea5e9',
            ])
            ->discoverResources(in: __DIR__ . '/Resources', for: 'OfficeGuy\\LaravelSumitGateway\\Filament\\Client\\Resources')
            ->discoverPages(in: __DIR__ . '/Pages', for: 'OfficeGuy\\LaravelSumitGateway\\Filament\\Client\\Pages')
            ->pages([])
            ->discoverWidgets(in: __DIR__ . '/Widgets', for: 'OfficeGuy\\LaravelSumitGateway\\Filament\\Client\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
