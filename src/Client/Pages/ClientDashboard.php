<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Widgets\ClientStatsOverview;

class ClientDashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public function getWidgets(): array
    {
        return [
            ClientStatsOverview::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return 4;
    }

    public function getTitle(): string
    {
        return 'My Payments Dashboard';
    }
}
