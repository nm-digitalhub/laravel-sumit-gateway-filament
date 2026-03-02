<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\PackageVersion;
use OfficeGuy\LaravelSumitGateway\Services\PackageVersionService;

/**
 * About Page - SUMIT Payment Gateway Package Information
 *
 * Displays information about the installed SUMIT Payment Gateway package:
 * - Current version installed
 * - Latest version available on Packagist
 * - Update status badge
 * - Links to documentation, changelog, and Packagist
 * - Package features and capabilities
 *
 * ## Architecture
 *
 * This page demonstrates the **correct separation of concerns**:
 *
 * **Package Layer** (this file):
 * - Displays version information from PackageVersionService
 * - Shows package metadata and features
 * - Provides links to external resources
 * - READ-ONLY - no notifications sent from here
 *
 * **Application Layer** (can extend/override):
 * - Can add custom notifications
 * - Can add admin alerts
 * - Can integrate with monitoring systems
 * - Can customize UI/UX
 */
class AboutPage extends Page
{
    /**
     * The page navigation sort order.
     */
    protected static ?int $navigationSort = 100;

    /**
     * The page navigation icon.
     */
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-information-circle';

    /**
     * The page navigation label.
     */
    protected static ?string $navigationLabel = 'אודות';

    /**
     * The page title (breadcrumb).
     */
    protected static ?string $title = 'אודות SUMIT Payment Gateway';

    /**
     * The cluster this page belongs to.
     *
     * Note: When using a cluster, navigationGroup is not needed.
     * The cluster provides the grouping automatically.
     */
    protected static ?string $cluster = \OfficeGuy\LaravelSumitGateway\Filament\Clusters\SumitGateway::class;

    /**
     * The view path for this page.
     */
    protected string $view = 'officeguy::filament.pages.about';

    /**
     * Get the package version status.
     *
     * Cached for 5 minutes within the page to avoid excessive service calls.
     */
    public function getVersionStatus(): PackageVersion
    {
        return Cache::remember('officeguy.about_page_version', 300, fn () => app(PackageVersionService::class)->getStatus());
    }

    /**
     * Get the package features list.
     *
     * @return array<string, string>
     */
    public function getFeatures(): array
    {
        return [
            'payments' => __('officeguy::officeguy.about.feature_payments'),
            'tokens' => __('officeguy::officeguy.about.feature_tokens'),
            'documents' => __('officeguy::officeguy.about.feature_documents'),
            'subscriptions' => __('officeguy::officeguy.about.feature_subscriptions'),
            'bit' => __('officeguy::officeguy.about.feature_bit'),
            'multi_vendor' => __('officeguy::officeguy.about.feature_multi_vendor'),
            'webhooks' => __('officeguy::officeguy.about.feature_webhooks'),
            'filament' => __('officeguy::officeguy.about.feature_filament'),
        ];
    }

    /**
     * Get the package statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        return [
            'total_downloads' => number_format(0), // TODO: Fetch from Packagist API if needed
            'monthly_downloads' => number_format(0),
            'github_stars' => 0,
            'license' => 'MIT',
            'php_version' => '^8.2',
            'laravel_version' => '^12.0',
            'filament_version' => '^4.0',
        ];
    }

    /**
     * Get the support links.
     *
     * @return array<string, string>
     */
    public function getSupportLinks(): array
    {
        return [
            'documentation' => 'https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel#readme',
            'issues' => 'https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel/issues',
            'discussions' => 'https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel/discussions',
            'packagist' => 'https://packagist.org/packages/officeguy/laravel-sumit-gateway',
            'sumit_api' => 'https://docs.sumit.co.il',
        ];
    }

    /**
     * Get the view data for the page.
     *
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'version' => $this->getVersionStatus(),
            'features' => $this->getFeatures(),
            'statistics' => $this->getStatistics(),
            'supportLinks' => $this->getSupportLinks(),
        ];
    }

    /**
     * Refresh the version information.
     *
     * Clears cache and fetches fresh data from Packagist.
     */
    public function refreshVersion(): void
    {
        Cache::forget('officeguy.about_page_version');
        app(PackageVersionService::class)->refresh();
    }

    /**
     * Get the badge color for the version status.
     */
    public function getBadgeColor(PackageVersion $version): string
    {
        return $version->getBadgeColor();
    }
}
