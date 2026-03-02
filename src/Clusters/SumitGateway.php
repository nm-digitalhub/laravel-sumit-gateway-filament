<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Clusters;

use Filament\Clusters\Cluster;

/**
 * SUMIT Gateway Admin Cluster
 *
 * Groups all SUMIT Gateway admin resources and pages together:
 * - Transactions (payment records)
 * - Tokens (saved payment methods)
 * - Documents (invoices/receipts)
 * - Subscriptions (recurring billing)
 * - Vendor Credentials (multi-vendor setup)
 * - Webhook Events (outgoing webhooks)
 * - SUMIT Webhooks (incoming webhooks)
 * - Settings Page
 * - About Page (package information & version status)
 */
class SumitGateway extends Cluster
{
    /**
     * Navigation icon in main admin sidebar
     * Uses Heroicon banknotes icon (payment gateway theme)
     */
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';

    /**
     * Navigation label in Hebrew
     */
    protected static ?string $navigationLabel = 'שער תשלומי SUMIT';

    /**
     * Navigation sort order
     */
    protected static ?int $navigationSort = 10;

    /**
     * Cluster breadcrumb label
     */
    protected static ?string $clusterBreadcrumb = 'SUMIT Gateway';

    /**
     * Get the cluster breadcrumb (for dynamic translation)
     */
    public static function getClusterBreadcrumb(): string
    {
        return __('SUMIT Gateway');
    }
}
