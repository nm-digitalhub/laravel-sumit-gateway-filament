@php
    $record = $getRecord();

    // Get request and response data
    $request = $record?->raw_request;
    $response = $record?->raw_response;

    // Normalize JSON strings
    if (is_string($request)) {
        $decoded = json_decode($request, true);
        $request = json_last_error() === JSON_ERROR_NONE ? $decoded : ['_raw' => $request];
    }

    if (is_string($response)) {
        $decoded = json_decode($response, true);
        $response = json_last_error() === JSON_ERROR_NONE ? $decoded : ['_raw' => $response];
    }

    if (!is_array($request)) {
        $request = [];
    }

    if (!is_array($response)) {
        $response = [];
    }
@endphp

<div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
    <x-officeguy::api-payload-diff
        :request="$request"
        :response="$response"
    />
</div>
