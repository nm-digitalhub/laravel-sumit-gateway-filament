<x-filament-panels::page>
    <div class="space-y-6">
        @if($resultStatus === 'success')
            {{-- Success State --}}
            @include('officeguy::components.success-card', [
                'token' => $resultData['token'],
                'owner' => $resultData['owner'],
                'setAsDefault' => $resultData['set_as_default']
            ])

            <div class="flex gap-4">
                <a href="{{ \OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource::getUrl('index') }}"
                   class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 transition">
                    View All Cards
                </a>
                <button wire:click="resetForm"
                        class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    Add Another Card
                </button>
            </div>

        @elseif($resultStatus === 'error')
            {{-- Error State --}}
            @include('officeguy::components.error-card', [
                'message' => $resultData['message'],
                'errorType' => $resultData['error_type']
            ])

            <div class="flex gap-4">
                <button wire:click="resetForm"
                        class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 transition">
                    Try Again
                </button>
                <a href="{{ \OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource::getUrl('index') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    Cancel
                </a>
            </div>

        @else
            {{-- Initial Form State --}}
            {{-- Instructions Card --}}
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100">How to Add a New Card</h3>
                        <p class="mt-1 text-sm text-blue-800 dark:text-blue-200">
                            Enter the card details below. All information is securely processed through SUMIT's encrypted payment gateway.
                        </p>
                    </div>
                </div>
            </div>

        {{-- Payment Form Card --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <form data-og="form" id="payment-form" wire:ignore.self>
                @csrf

                {{-- Error Display --}}
                <div class="og-errors mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-800 dark:text-red-200"></div>

                <div class="space-y-4">
                    {{-- Card Number --}}
                    <div>
                        <label for="card-number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Card Number
                        </label>
                        <input
                            type="text"
                            name="og-ccnum"
                            data-og="cardnumber"
                            id="card-number"
                            maxlength="20"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                            placeholder="•••• •••• •••• ••••"
                        />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        {{-- Expiry Month --}}
                        <div>
                            <label for="expiry-month" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Month
                            </label>
                            <input
                                type="text"
                                name="og-expmonth"
                                data-og="expirationmonth"
                                id="expiry-month"
                                maxlength="2"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                                placeholder="MM"
                            />
                        </div>

                        {{-- Expiry Year --}}
                        <div>
                            <label for="expiry-year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Year
                            </label>
                            <input
                                type="text"
                                name="og-expyear"
                                data-og="expirationyear"
                                id="expiry-year"
                                maxlength="4"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                                placeholder="YYYY"
                            />
                        </div>

                        {{-- CVV --}}
                        <div>
                            <label for="cvv" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                CVV
                            </label>
                            <input
                                type="text"
                                name="og-cvv"
                                data-og="cvv"
                                id="cvv"
                                maxlength="4"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                                placeholder="•••"
                            />
                        </div>
                    </div>

                    {{-- Citizen ID --}}
                    <div>
                        <label for="citizen-id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            ID Number (תעודת זהות)
                        </label>
                        <input
                            type="text"
                            name="og-citizenid"
                            data-og="citizenid"
                            id="citizen-id"
                            maxlength="9"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                            placeholder="123456789"
                        />
                    </div>

                    {{-- Set as Default --}}
                    <div class="flex items-center">
                        <input
                            type="checkbox"
                            id="set_as_default"
                            name="set_as_default"
                            checked
                            class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50 dark:border-gray-600 dark:bg-gray-700"
                        >
                        <label for="set_as_default" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                            Set as default payment method
                        </label>
                    </div>

                    {{-- Hidden fields for tokens --}}
                    <input type="hidden" name="og-token" id="og-token">
                    <input type="hidden" name="single_use_token" id="single_use_token">

                    {{-- Submit Button --}}
                    <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a
                            href="{{ \OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource::getUrl('index') }}"
                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200"
                        >
                            Cancel
                        </a>
                        <button
                            type="submit"
                            id="submit-button"
                            class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 active:bg-primary-900 focus:outline-none focus:border-primary-900 focus:ring focus:ring-primary-300 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        >
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white hidden" id="loading-spinner" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Add Payment Card
                        </button>
                    </div>
                </div>
            </form>
        </div>

            {{-- Security Notice --}}
            <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    <div class="flex-1">
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            🔒 Your card information is encrypted and securely transmitted to SUMIT.
                            This application never stores your full card number or CVV.
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
    {{-- Load jQuery first (SUMIT SDK requires it) --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    {{-- Load SUMIT Payments SDK --}}
    <script src="https://app.sumit.co.il/scripts/payments.js"></script>

    <script>
        // Function to initialize SUMIT payments
        function initializeSumitPayments() {
            // Check if jQuery is available
            if (typeof jQuery === 'undefined') {
                console.log('jQuery not yet loaded, retrying...');
                setTimeout(initializeSumitPayments, 100);
                return;
            }

            // Check if SUMIT SDK is loaded
            if (typeof OfficeGuy === 'undefined' || !OfficeGuy.Payments) {
                console.log('SUMIT SDK not yet loaded, retrying...');
                setTimeout(initializeSumitPayments, 100);
                return;
            }

            console.log('jQuery and SUMIT SDK loaded, initializing...');

            jQuery(function($) {
                const companyId = {{ config('officeguy.company_id') }};
                const publicKey = '{{ $this->getPublicKey() }}';
                const environment = '{{ $this->getEnvironment() }}';
                const submitButton = document.getElementById('submit-button');
                const loadingSpinner = document.getElementById('loading-spinner');
                const form = $('#payment-form');
                const setAsDefaultCheckbox = document.getElementById('set_as_default');

                let isProcessing = false;

                console.log('SUMIT Payments ready - Company ID:', companyId);

                // Handle form submission using CreateToken API (like WooCommerce plugin)
                form.on('submit', function(e) {
                    e.preventDefault();

                    if (isProcessing) {
                        console.log('Already processing, ignoring duplicate submission');
                        return false;
                    }

                    // Clear previous errors
                    $('.og-errors').empty().hide();

                    // Validate required fields
                    let isValid = true;
                    const cardNumber = $('input[data-og="cardnumber"]').val().trim();
                    const expiryMonth = $('input[data-og="expirationmonth"]').val().trim();
                    const expiryYear = $('input[data-og="expirationyear"]').val().trim();
                    const cvv = $('input[data-og="cvv"]').val().trim();
                    const citizenId = $('input[data-og="citizenid"]').val().trim();

                    if (!cardNumber) {
                        $('.og-errors').show().append('<div>Card number is required</div>');
                        isValid = false;
                    }
                    if (!expiryMonth || !expiryYear) {
                        $('.og-errors').show().append('<div>Expiry date is required</div>');
                        isValid = false;
                    }
                    if (!cvv) {
                        $('.og-errors').show().append('<div>CVV is required</div>');
                        isValid = false;
                    }
                    if (!citizenId) {
                        $('.og-errors').show().append('<div>ID number is required</div>');
                        isValid = false;
                    }

                    if (!isValid) {
                        return false;
                    }

                    isProcessing = true;
                    submitButton.disabled = true;
                    loadingSpinner.classList.remove('hidden');

                    console.log('Creating token with SUMIT API...');

                    // Use CreateToken API with callback (proven to work in WooCommerce plugin)
                    const settings = {
                        FormSelector: form,
                        CompanyID: companyId,
                        APIPublicKey: publicKey,
                        Environment: environment,
                        ResponseLanguage: 'he',
                        Callback: function(tokenValue) {
                            console.log('SUMIT Callback received, token:', tokenValue ? 'Success' : 'Failed');

                            if (tokenValue != null && tokenValue !== '') {
                                // Token created successfully
                                console.log('Token created successfully, processing with Livewire...');

                                // Set the token value
                                $('#single_use_token').val(tokenValue);

                                // Get set_as_default value
                                const setAsDefault = setAsDefaultCheckbox ? setAsDefaultCheckbox.checked : true;
                                console.log('Checkbox element:', setAsDefaultCheckbox);
                                console.log('Checkbox checked state:', setAsDefault);

                                // Submit via Livewire
                                const component = window.Livewire.find('{{ $this->getId() }}');
                                if (component) {
                                    console.log('Setting Livewire properties:', {
                                        singleUseToken: tokenValue.substring(0, 20) + '...',
                                        setAsDefault: setAsDefault
                                    });

                                    // Set both values and wait for them to sync
                                    component.set('singleUseToken', tokenValue);
                                    component.set('setAsDefault', setAsDefault);

                                    // Wait for next tick to ensure values are set
                                    setTimeout(function() {
                                        console.log('Calling processNewCard after 100ms delay');
                                        console.log('Current component state:', {
                                            singleUseToken: component.get('singleUseToken') ? component.get('singleUseToken').substring(0, 20) + '...' : 'null',
                                            setAsDefault: component.get('setAsDefault')
                                        });

                                        component.call('processNewCard').then(function() {
                                            console.log('Card processed successfully via Livewire');
                                            isProcessing = false;
                                        }).catch(function(error) {
                                            console.error('Livewire processing error:', error);
                                            isProcessing = false;
                                            submitButton.disabled = false;
                                            loadingSpinner.classList.add('hidden');

                                            $('.og-errors').show().append('<div>Processing failed. Please try again.</div>');
                                        });
                                    }, 100);
                                } else {
                                    console.error('Livewire component not found');
                                    isProcessing = false;
                                    submitButton.disabled = false;
                                    loadingSpinner.classList.add('hidden');

                                    $('.og-errors').show().append('<div>System error. Please refresh the page.</div>');
                                }
                            } else {
                                // Token creation failed
                                console.error('Token creation failed');
                                isProcessing = false;
                                submitButton.disabled = false;
                                loadingSpinner.classList.add('hidden');

                                // Check if error message is in .og-errors (SUMIT SDK adds it automatically)
                                if ($('.og-errors').text().trim() === '') {
                                    $('.og-errors').show().append('<div>Card validation failed. Please check your details.</div>');
                                } else {
                                    $('.og-errors').show();
                                }
                            }
                        }
                    };

                    // Call CreateToken and prevent default form submission
                    if (OfficeGuy.Payments.CreateToken(settings)) {
                        // CreateToken returned true - token was retrieved from cache
                        console.log('Token retrieved from cache');
                        isProcessing = false;
                        return true;
                    }

                    // CreateToken is processing asynchronously - callback will be called
                    return false;
                });
            });
        }

        // Initialize once when ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeSumitPayments);
        } else {
            // DOM already loaded, start immediately
            initializeSumitPayments();
        }
    </script>
    @endpush
</x-filament-panels::page>
