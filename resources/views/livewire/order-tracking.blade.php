<div class="min-h-screen bg-stone-50 py-12 px-4 sm:px-6 lg:px-8"
     wire:poll.15s="checkStatus"
     x-data="{
        live: false,
        init() {
            const boot = () => {
                if (! window.Echo) { setTimeout(boot, 300); return; }
                window.Echo.channel('order.{{ $invoice->table?->uuid }}.{{ $invoice->id }}')
                    .listen('OrderStatusUpdated', () => $wire.checkStatus())
                    .listen('PaymentReceived', () => $wire.checkStatus());
                const conn = window.Echo.connector.pusher.connection;
                this.live = conn.state === 'connected';
                conn.bind('state_change', ({ current }) => {
                    const wasOffline = ! this.live;
                    this.live = current === 'connected';
                    if (this.live && wasOffline) $wire.checkStatus();
                });
            };
            boot();
        }
     }">
    <div class="max-w-3xl mx-auto space-y-8">

        <!-- Header -->
        <div class="text-center">
            <h1 class="text-3xl font-extrabold text-stone-900 tracking-tight">Order #{{ str_pad($invoice->id, 5, '0', STR_PAD_LEFT) }}</h1>
            <p class="mt-2 text-lg text-stone-500">Table {{ $invoice->table->name ?? 'N/A' }}</p>
            <div class="mt-2 flex items-center justify-center gap-3">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold" :class="live ? 'text-green-600' : 'text-stone-400'">
                    <span class="w-2 h-2 rounded-full" :class="live ? 'bg-green-500 animate-pulse' : 'bg-stone-300'"></span>
                    <span x-text="live ? 'Live updates' : 'Checking periodically'"></span>
                </span>
                @if ($this->isPaid)
                    <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-xs font-bold px-2.5 py-1 rounded-full">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                        Paid
                    </span>
                @elseif ($this->pendingKhqrSvg)
                    <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 text-xs font-bold px-2.5 py-1 rounded-full">
                        Scan QR to pay
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 text-xs font-bold px-2.5 py-1 rounded-full">
                        Pay at counter
                    </span>
                @endif
            </div>
        </div>

        <!-- Status Card -->
        <div class="bg-white rounded-3xl shadow-xl border border-stone-100 overflow-hidden transform transition-all duration-500 hover:shadow-2xl">
            <div class="p-8">
                <!-- Status Timeline -->
                <div class="relative">
                    <div class="overflow-hidden h-2 mb-4 text-xs flex rounded-full bg-amber-50">
                        @php
                            $statusPercent = match($invoice->status) {
                                'pending' => 25,
                                'preparing' => 50,
                                'ready' => 75,
                                'completed' => 100,
                                default => 0,
                            };
                        @endphp
                        <div style="width: {{ $statusPercent }}%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-amber-500 transition-all duration-1000"></div>
                    </div>
                    
                    <div class="flex justify-between text-xs font-semibold text-stone-400 mt-2">
                        <div class="text-center w-1/4 {{ in_array($invoice->status, ['pending', 'preparing', 'ready', 'completed']) ? 'text-amber-600' : '' }}">
                            <div class="mx-auto flex items-center justify-center w-8 h-8 rounded-full mb-1 {{ in_array($invoice->status, ['pending', 'preparing', 'ready', 'completed']) ? 'bg-amber-100' : 'bg-stone-100' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            Received
                        </div>
                        <div class="text-center w-1/4 {{ in_array($invoice->status, ['preparing', 'ready', 'completed']) ? 'text-amber-600' : '' }}">
                            <div class="mx-auto flex items-center justify-center w-8 h-8 rounded-full mb-1 {{ in_array($invoice->status, ['preparing', 'ready', 'completed']) ? 'bg-amber-100' : 'bg-stone-100' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14v6m-3-3h6M6 10h2a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2zm10 0h2a2 2 0 002-2V6a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2zM6 20h2a2 2 0 002-2v-2a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 002 2z"></path></svg>
                            </div>
                            Preparing
                        </div>
                        <div class="text-center w-1/4 {{ in_array($invoice->status, ['ready', 'completed']) ? 'text-amber-600' : '' }}">
                            <div class="mx-auto flex items-center justify-center w-8 h-8 rounded-full mb-1 {{ in_array($invoice->status, ['ready', 'completed']) ? 'bg-amber-100' : 'bg-stone-100' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            Ready
                        </div>
                        <div class="text-center w-1/4 {{ $invoice->status === 'completed' ? 'text-amber-600' : '' }}">
                            <div class="mx-auto flex items-center justify-center w-8 h-8 rounded-full mb-1 {{ $invoice->status === 'completed' ? 'bg-amber-100' : 'bg-stone-100' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            Served
                        </div>
                    </div>
                </div>

                <!-- Current Status Text -->
                <div class="mt-8 text-center bg-amber-50 rounded-2xl py-6 px-4">
                    <p class="text-sm font-semibold text-amber-800 uppercase tracking-wider mb-1">Current Status</p>
                    <h2 class="text-3xl font-black text-amber-900 capitalize">{{ $invoice->status }}</h2>
                    <p class="mt-2 text-amber-700 max-w-sm mx-auto">
                        @if($invoice->status === 'pending')
                            Your order has been received and will be prepared shortly.
                        @elseif($invoice->status === 'preparing')
                            Our chefs are crafting your order right now.
                        @elseif($invoice->status === 'ready')
                            Your order is ready to be served!
                        @else
                            Enjoy your meal!
                        @endif
                    </p>
                </div>
            </div>
            
            <!-- Order Details -->
            <div class="bg-stone-50 p-8 border-t border-stone-100">
                <h3 class="text-lg font-bold text-stone-900 mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5 text-stone-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    Order Details
                </h3>
                
                <div class="space-y-4 mb-6">
                    @foreach($invoice->orderItems as $item)
                        <div class="flex justify-between items-center text-stone-800">
                            <div class="flex items-center gap-3">
                                <span class="bg-white px-2.5 py-1 rounded-md text-sm font-bold shadow-sm border border-stone-200">{{ $item->quantity }}x</span>
                                <span class="font-medium">{{ $item->product->name ?? 'Unknown Product' }}</span>
                            </div>
                            <span class="font-bold text-stone-900">${{ number_format($item->subtotal, 2) }}</span>
                        </div>
                    @endforeach
                </div>
                
                <div class="border-t border-stone-200 pt-6 space-y-3">
                    <div class="flex justify-between text-stone-500">
                        <span>Subtotal</span>
                        <span>${{ number_format($invoice->subtotal, 2) }}</span>
                    </div>
                    @if($invoice->tax_total > 0)
                    <div class="flex justify-between text-stone-500">
                        <span>Tax</span>
                        <span>${{ number_format($invoice->tax_total, 2) }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-xl font-bold text-stone-900 pt-2 border-t border-stone-100 mt-2">
                        <span>Total</span>
                        <span class="text-amber-600">${{ number_format($invoice->total, 2) }}</span>
                    </div>
                </div>
            </div>
            
            @if($this->pendingKhqrSvg)
                <!-- KHQR Payment -->
                <div class="bg-white p-8 border-t border-stone-100 flex flex-col items-center justify-center">
                    <h3 class="text-lg font-bold text-stone-900 mb-2">Scan to Pay</h3>
                    <p class="text-sm text-stone-500 mb-6 text-center">Wait for our staff to confirm your payment after you scan.</p>
                    
                    <div class="bg-white p-3 rounded-2xl shadow-sm border border-stone-100 inline-block mb-4">
                        {!! $this->pendingKhqrSvg !!}
                    </div>
                    
                    <div class="text-2xl font-black text-amber-600">
                        ${{ number_format($invoice->total, 2) }}
                    </div>
                </div>
            @endif
        </div>
        
        <div class="text-center print:hidden flex items-center justify-center gap-6">
            @if($invoice->table && $invoice->table->uuid)
                <a href="{{ route('emenu.table', ['uuid' => $invoice->table->uuid]) }}" class="inline-flex items-center gap-2 text-amber-600 hover:text-amber-800 font-semibold transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to Menu
                </a>
            @endif
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 text-stone-500 hover:text-stone-700 font-semibold transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Print Receipt
            </button>
        </div>

    </div>
</div>
