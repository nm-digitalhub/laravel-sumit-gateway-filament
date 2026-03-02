<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\SumitWebhookResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\SumitWebhookResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\SumitWebhookResource\Widgets\SumitWebhookStatsOverview;

class ListSumitWebhooks extends ListRecords
{
    protected static string $resource = SumitWebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->resetTable()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SumitWebhookStatsOverview::class,
        ];
    }
}
