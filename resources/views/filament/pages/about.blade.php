<x-filament-panels::page>
    @php
        $version = $this->getVersionStatus();
        $badgeColor = $this->getBadgeColor($version);
    @endphp

    <div class="space-y-6">
        {{-- Header Section --}}
        <x-filament::section>
            <div class="text-center">
                <div class="flex items-center justify-center mb-4">
                    <!-- SUMIT Logo -->
                    <img
                        src="{{ asset('vendor/officeguy/assets/sumit-logo.svg') }}"
                        alt="SUMIT Payment Gateway"
                        class="h-20 w-20"
                    />
                </div>

                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ __('officeguy::officeguy.about.title') }}
                </h1>

                <p class="text-gray-600 dark:text-gray-400 mt-2">
                    {{ __('officeguy::officeguy.about.description') }}
                </p>
            </div>
        </x-filament::section>

        {{-- Version Status --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('officeguy::officeguy.about.title_version') }}
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Installed Version --}}
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ __('officeguy::officeguy.about.installed_version') }}
                    </dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $version->installed }}
                    </dd>
                </div>

                {{-- Latest Version --}}
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ __('officeguy::officeguy.about.latest_version') }}
                    </dt>
                    <dd class="mt-1 flex items-center gap-2">
                        <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ $version->latest }}
                        </span>

                        @if($version->outdated)
                            <x-filament::badge
                                :color="$badgeColor"
                                :label="$version->getStatusMessage(app()->getLocale())"
                            />
                        @else
                            <x-filament::badge
                                color="success"
                                :label="$version->getStatusMessage(app()->getLocale())"
                            />
                        @endif
                    </dd>
                </div>
            </div>

            {{-- Action Buttons --}}
            @if($version->outdated)
                <div class="mt-4 flex items-center gap-2">
                    <x-filament::button
                        url="{{ $version->latestUrl }}"
                        target="_blank"
                        icon="heroicon-o-arrow-top-right-on-square"
                    >
                        {{ __('officeguy::officeguy.about.view_on_packagist') }}
                    </x-filament::button>

                    <x-filament::button
                        url="{{ $version->changelogUrl }}"
                        target="_blank"
                        icon="heroicon-o-document-text"
                        color="gray"
                    >
                        {{ __('officeguy::officeguy.about.view_changelog') }}
                    </x-filament::button>
                </div>
            @endif

            {{-- Refresh Button --}}
            <div class="mt-4">
                <x-filament::button
                    wire:click="refreshVersion"
                    icon="heroicon-o-arrow-path"
                    color="gray"
                >
                    {{ __('officeguy::officeguy.about.refresh_version') }}
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- Features --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('officeguy::officeguy.about.title_features') }}
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($features as $key => $feature)
                    <div class="flex items-start gap-3">
                        <x-filament::icon
                            icon="heroicon-o-check-circle"
                            class="w-5 h-5 text-success-500 flex-shrink-0 mt-0.5"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            {{ $feature }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Statistics --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('officeguy::officeguy.about.title_statistics') }}
            </x-slot>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ __('officeguy::officeguy.about.license') }}
                    </dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $statistics['license'] }}
                    </dd>
                </div>

                <div class="text-center">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        PHP
                    </dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $statistics['php_version'] }}
                    </dd>
                </div>

                <div class="text-center">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Laravel
                    </dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $statistics['laravel_version'] }}
                    </dd>
                </div>

                <div class="text-center">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Filament
                    </dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $statistics['filament_version'] }}
                    </dd>
                </div>
            </div>
        </x-filament::section>

        {{-- Support Links --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('officeguy::officeguy.about.title_support') }}
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($supportLinks as $key => $url)
                    <x-filament::button
                        :url="$url"
                        target="_blank"
                        icon="heroicon-o-arrow-top-right-on-square"
                        color="gray"
                        class="w-full justify-start"
                    >
                        @if($key === 'documentation')
                            {{ __('officeguy::officeguy.about.link_documentation') }}
                        @elseif($key === 'issues')
                            {{ __('officeguy::officeguy.about.link_issues') }}
                        @elseif($key === 'discussions')
                            {{ __('officeguy::officeguy.about.link_discussions') }}
                        @elseif($key === 'packagist')
                            {{ __('officeguy::officeguy.about.link_packagist') }}
                        @elseif($key === 'sumit_api')
                            {{ __('officeguy::officeguy.about.link_sumit_api') }}
                        @else
                            {{ $key }}
                        @endif
                    </x-filament::button>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Footer --}}
        <x-filament::section>
            <div class="text-center text-sm text-gray-500 dark:text-gray-400">
                <p>
                    {{ __('officeguy::officeguy.about.copyright') }}
                    <a
                        href="{{ $supportLinks['packagist'] }}"
                        target="_blank"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400"
                    >
                        NM-DigitalHub
                    </a>
                </p>
                <p class="mt-1">
                    {{ __('officeguy::officeguy.about.made_with') }}
                    <a
                        href="https://filamentphp.com"
                        target="_blank"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400"
                    >
                        Filament
                    </a>
                </p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
