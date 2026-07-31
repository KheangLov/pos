<div class="flex flex-col items-center justify-center p-6 text-center space-y-4">
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(250)->generate($url) !!}
    </div>
    
    <div class="max-w-xs mx-auto">
        <p class="text-sm text-gray-500 font-medium mb-2">Scan to access the eMenu for this table</p>
        <a href="{{ $url }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-sm font-semibold break-all">{{ $url }}</a>
    </div>
    
    <div class="mt-4 pt-4 border-t border-gray-100 w-full">
        <x-filament::button
            tag="a"
            href="data:image/svg+xml;base64,{{ base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(500)->generate($url)) }}"
            download="table-qr-code.svg"
            color="primary"
            icon="heroicon-o-arrow-down-tray"
        >
            Download QR Code
        </x-filament::button>
    </div>
</div>
