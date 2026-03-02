@php
    $url = data_get($getRecord()->raw_response, 'Data.DocumentDownloadURL');

    // Extract document number from URL
    preg_match('/download=(\d+)/', $url ?? '', $matches);
    $docNumber = $matches[1] ?? 'לא זמין';

    // Get document type from transaction
    $docType = match($getRecord()->document_id ?? null) {
        null => 'מסמך',
        default => 'חשבונית',
    };

    $date = $getRecord()->created_at;
@endphp

<div class="rounded-xl border-2 border-primary-200 dark:border-primary-800 bg-gradient-to-br from-primary-50 to-white dark:from-primary-950 dark:to-gray-900 p-5 shadow-sm hover:shadow-md transition-shadow">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-4">
        <div class="rounded-lg bg-primary-100 dark:bg-primary-900 p-2.5">
            <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
        </div>

        <div class="flex-1">
            <h4 class="font-semibold text-gray-900 dark:text-white text-lg">
                {{ $docType }} מס' {{ $docNumber }}
            </h4>
            <p class="text-sm text-gray-600 dark:text-gray-400 flex items-center gap-1.5 mt-0.5">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
                נוצר: {{ $date->format('d/m/Y H:i') }}
            </p>
        </div>

        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 border border-green-200 dark:border-green-800">
            <svg class="w-3.5 h-3.5 ml-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            זמין להורדה
        </span>
    </div>

    {{-- Actions --}}
    <div class="flex flex-wrap gap-2">
        {{-- Download Button --}}
        <a href="{{ $url }}"
           download
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors shadow-sm hover:shadow">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            הורד מסמך
        </a>

        {{-- Open in New Tab --}}
        <a href="{{ $url }}"
           target="_blank"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium rounded-lg border border-gray-300 dark:border-gray-600 transition-colors">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
            </svg>
            פתח בחלון חדש
        </a>

        {{-- Copy Link --}}
        <button type="button"
                onclick="copyDocumentLink(this, '{{ $url }}')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium rounded-lg border border-gray-300 dark:border-gray-600 transition-colors">
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
            </svg>
            <span class="copy-text">העתק קישור</span>
        </button>
    </div>
</div>

@once
    @push('scripts')
    <script>
    function copyDocumentLink(button, url) {
        navigator.clipboard.writeText(url).then(() => {
            const span = button.querySelector('.copy-text');
            const original = span.textContent;
            span.textContent = '✓ הועתק!';

            // Add success styling
            button.classList.add('bg-green-50', 'dark:bg-green-900/20', 'border-green-300', 'dark:border-green-700', 'text-green-700', 'dark:text-green-400');

            setTimeout(() => {
                span.textContent = original;
                button.classList.remove('bg-green-50', 'dark:bg-green-900/20', 'border-green-300', 'dark:border-green-700', 'text-green-700', 'dark:text-green-400');
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy:', err);
            alert('שגיאה בהעתקת הקישור');
        });
    }
    </script>
    @endpush
@endonce
