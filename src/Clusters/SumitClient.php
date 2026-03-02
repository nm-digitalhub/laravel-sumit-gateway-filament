<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Clusters;

use Filament\Clusters\Cluster;

/**
 * SUMIT Client Cluster
 *
 * Groups all SUMIT Gateway client panel resources together:
 * - Payment Methods (customer saved cards)
 * - Transactions (customer payment history)
 * - Documents (customer invoices/receipts)
 * - Subscriptions (customer recurring billing)
 * - Webhook Events (customer outgoing webhooks)
 * - SUMIT Webhooks (customer incoming webhooks)
 */
class SumitClient extends Cluster
{
    /**
     * Navigation icon in client panel sidebar
     */
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-wallet';

    /**
     * Navigation label in Hebrew
     */
    protected static ?string $navigationLabel = 'ניהול תשלומים';

    /**
     * Navigation sort order
     */
    protected static ?int $navigationSort = 20;

    /**
     * Cluster breadcrumb label
     */
    protected static ?string $clusterBreadcrumb = 'ניהול תשלומים';

    /**
     * Get the cluster breadcrumb (for dynamic translation)
     */
    public static function getClusterBreadcrumb(): string
    {
        return __('ניהול תשלומים');
    }
}
