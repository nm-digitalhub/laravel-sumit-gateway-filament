<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

class ClientStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $userId = auth()->id();

        if (! $userId) {
            return [];
        }

        // Get user's transactions
        $totalTransactions = OfficeGuyTransaction::where('customer_id', $userId)->count();
        $completedTransactions = OfficeGuyTransaction::where('customer_id', $userId)
            ->where('status', 'completed')
            ->count();

        // Get total spent amount
        $totalSpent = OfficeGuyTransaction::where('customer_id', $userId)
            ->where('status', 'completed')
            ->sum('amount');

        // Get user's documents
        $documentsCount = OfficeGuyDocument::where('customer_id', $userId)->count();

        // Get client's saved payment methods
        $client = auth()->user()?->client;
        $savedCardsCount = 0;
        if ($client) {
            $savedCardsCount = OfficeGuyToken::where('owner_type', $client::class)
                ->where('owner_id', $client->id)
                ->count();
        }

        return [
            Stat::make('Total Transactions', $totalTransactions)
                ->description($completedTransactions . ' completed')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Total Spent', '₪' . number_format((float) $totalSpent, 2))
                ->description('Across all purchases')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('primary'),

            Stat::make('Documents', $documentsCount)
                ->description('Invoices & Receipts')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),

            Stat::make('Saved Cards', $savedCardsCount)
                ->description('Payment methods')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('warning'),
        ];
    }
}
