@php
    $licenses = app(\App\Services\LicenseManager::class);
    $status = $licenses->status();
    $message = $licenses->message();
    // Only nag on a licensed deployment; a dev machine with enforcement off
    // shouldn't carry a permanent "no licence" bar.
    $show = config('license.enforce') && $status->needsAttention() && filled($message);
    $urgent = $status === \App\Support\LicenseStatus::Grace;
@endphp

@if ($show)
    {{-- Inline styles so this renders without depending on a Vite rebuild. --}}
    <div style="
        padding: 0.625rem 1rem;
        text-align: center;
        font-size: 0.8125rem;
        font-weight: 500;
        line-height: 1.4;
        color: {{ $urgent ? '#7f1d1d' : '#78350f' }};
        background: {{ $urgent ? '#fee2e2' : '#fef3c7' }};
        border-bottom: 1px solid {{ $urgent ? '#fca5a5' : '#fcd34d' }};
    ">
        <strong>{{ $status->label() }}.</strong> {{ $message }}
    </div>
@endif
