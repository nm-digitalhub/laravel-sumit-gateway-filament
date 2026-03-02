@php
    $state = $getState();

    // Normalize: allow JSON string / array / null
    if (is_string($state)) {
        $decoded = json_decode($state, true);
        $state = json_last_error() === JSON_ERROR_NONE ? $decoded : ['_raw' => $state];
    }

    if (! is_array($state)) {
        $state = [];
    }
@endphp

<div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
    @if(empty($state))
        <div class="text-sm text-gray-400 italic dark:text-gray-500">אין נתוני API</div>
    @else
        <x-officeguy::api-payload
            :value="$state"
            :highlight="['Payment', 'Customer', 'Errors', 'Error', 'Status', 'Data', 'Amount', 'TransactionID']"
        />
    @endif
</div>
