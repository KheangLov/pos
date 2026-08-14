<x-filament-panels::page>
    @vite(['resources/js/app.js'])
    {{-- wire:poll = polling fallback when Reverb is unreachable; Echo updates
         still arrive instantly and the poll only re-renders (P2). --}}
    <div class="h-[calc(100vh-12rem)]" x-data="kdsBoard({{ $branchId }})" wire:key="kds-board" wire:poll.15s>

        {{-- Connection banner --}}
        <div x-show="!connected" x-cloak
             class="mb-4 flex items-center gap-3 rounded-xl border border-red-300 bg-red-50 dark:bg-red-950/40 dark:border-red-800 px-4 py-3 text-red-700 dark:text-red-300">
            <span class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
            </span>
            <span class="font-semibold text-sm">Connection lost — reconnecting… New orders may be delayed.</span>
        </div>

        @if ($orders->isEmpty())
            <div class="h-full flex flex-col items-center justify-center text-gray-400 dark:text-gray-500 space-y-4">
                <x-heroicon-o-fire class="w-20 h-20 opacity-15" />
                <p class="text-lg font-semibold">All caught up — no active orders.</p>
                <p class="text-sm">New orders will appear here instantly.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 h-full overflow-y-auto custom-scrollbar p-2 items-start">
                @foreach ($orders as $order)
                    <div wire:key="order-{{ $order->id }}-{{ $order->status }}"
                         class="bg-white dark:bg-gray-900 border-2 rounded-2xl flex flex-col overflow-hidden shadow-sm transition-all"
                         :class="ageClass('{{ $order->created_at->toIso8601String() }}', '{{ $order->status }}').border">

                        {{-- Header --}}
                        <div class="p-4 flex justify-between items-center text-white"
                             :class="ageClass('{{ $order->created_at->toIso8601String() }}', '{{ $order->status }}').header">
                            <div class="font-bold text-lg">Order #{{ $order->id }}</div>
                            <div class="font-bold bg-black/20 px-2 py-1 rounded-md text-sm">
                                {{ $order->table?->name ?? 'Counter' }}
                            </div>
                        </div>

                        <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800 text-xs font-semibold text-gray-500 flex justify-between items-center border-b border-gray-100 dark:border-gray-700">
                            <span class="tabular-nums font-mono text-sm font-bold"
                                  :class="ageClass('{{ $order->created_at->toIso8601String() }}', '{{ $order->status }}').text"
                                  x-text="ageLabel('{{ $order->created_at->toIso8601String() }}')"></span>
                            <span class="px-2 py-0.5 rounded-full uppercase tracking-wider
                                @if ($order->status === 'pending') bg-amber-100 text-amber-700
                                @elseif ($order->status === 'preparing') bg-blue-100 text-blue-700
                                @else bg-green-100 text-green-700 @endif">
                                {{ $order->status }}
                            </span>
                        </div>

                        {{-- Items --}}
                        <div class="flex-1 p-4 space-y-3 overflow-y-auto max-h-64">
                            @foreach ($order->orderItems as $item)
                                <div class="flex justify-between items-center pb-2 border-b border-gray-100 dark:border-gray-800 last:border-0 last:pb-0">
                                    <div>
                                        <span class="font-medium text-gray-900 dark:text-gray-100 text-lg leading-tight">
                                            {{ $item->product?->name ?? 'Item' }}
                                        </span>
                                        @if ($item->productVariant)
                                            <span class="block text-xs text-gray-400 font-semibold uppercase">{{ $item->productVariant->name }}</span>
                                        @endif
                                        @if ($item->modifiers->isNotEmpty())
                                            <span class="block text-xs text-primary-600 dark:text-primary-400 font-medium">{{ $item->modifiers->pluck('name')->implode(', ') }}</span>
                                        @endif
                                        @if ($item->notes)
                                            <span class="block text-xs text-amber-600 italic">“{{ $item->notes }}”</span>
                                        @endif
                                    </div>
                                    <span class="font-bold text-gray-500 bg-gray-100 dark:bg-gray-800 px-3 py-1 rounded-lg text-lg shrink-0">x{{ $item->quantity }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Actions --}}
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-800">
                            @if ($order->status === 'pending')
                                <button wire:click="setStatus({{ $order->id }}, 'preparing')" wire:loading.attr="disabled" wire:target="setStatus({{ $order->id }}, 'preparing')"
                                        class="w-full py-3 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl shadow-sm transition-colors text-lg disabled:opacity-70 disabled:cursor-wait flex items-center justify-center gap-2">
                                    <x-filament::loading-indicator wire:loading wire:target="setStatus({{ $order->id }}, 'preparing')" class="w-5 h-5" />
                                    <span wire:loading.remove wire:target="setStatus({{ $order->id }}, 'preparing')">Start Preparing</span>
                                    <span wire:loading wire:target="setStatus({{ $order->id }}, 'preparing')">Starting…</span>
                                </button>
                            @elseif ($order->status === 'preparing')
                                <button wire:click="setStatus({{ $order->id }}, 'ready')" wire:loading.attr="disabled" wire:target="setStatus({{ $order->id }}, 'ready')"
                                        class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-sm transition-colors text-lg disabled:opacity-70 disabled:cursor-wait flex items-center justify-center gap-2">
                                    <x-filament::loading-indicator wire:loading wire:target="setStatus({{ $order->id }}, 'ready')" class="w-5 h-5" />
                                    <span wire:loading.remove wire:target="setStatus({{ $order->id }}, 'ready')">Mark as Ready</span>
                                    <span wire:loading wire:target="setStatus({{ $order->id }}, 'ready')">Updating…</span>
                                </button>
                            @else
                                <button wire:click="setStatus({{ $order->id }}, 'completed')" wire:loading.attr="disabled" wire:target="setStatus({{ $order->id }}, 'completed')"
                                        class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl shadow-sm transition-colors text-lg disabled:opacity-70 disabled:cursor-wait flex items-center justify-center gap-2">
                                    <x-filament::loading-indicator wire:loading wire:target="setStatus({{ $order->id }}, 'completed')" class="w-5 h-5" />
                                    <span wire:loading.remove wire:target="setStatus({{ $order->id }}, 'completed')">Served ✓</span>
                                    <span wire:loading wire:target="setStatus({{ $order->id }}, 'completed')">Serving…</span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <script>
        function kdsBoard(branchId) {
            return {
                connected: true,
                now: Date.now(),
                _timer: null,

                init() {
                    this._timer = setInterval(() => { this.now = Date.now(); }, 1000);

                    const boot = () => {
                        if (! window.Echo) { setTimeout(boot, 300); return; }

                        const channel = `branch.${branchId}.kds`;
                        window.Echo.private(channel)
                            .listen('OrderCreated', () => { this.chime(); this.$wire.$refresh(); })
                            .listen('OrderStatusUpdated', () => this.$wire.$refresh())
                            .listen('PaymentReceived', () => this.$wire.$refresh());

                        // SPA navigation keeps the page alive — leave the channel so
                        // listeners don't stack up on revisits
                        document.addEventListener('livewire:navigating', () => {
                            clearInterval(this._timer);
                            window.Echo.leave(channel);
                        }, { once: true });

                        const conn = window.Echo.connector.pusher.connection;
                        conn.bind('state_change', ({ current }) => {
                            const wasDisconnected = ! this.connected;
                            this.connected = current === 'connected';
                            // Refetch after reconnect so no orders are missed
                            if (this.connected && wasDisconnected) this.$wire.$refresh();
                        });
                    };
                    boot();
                },

                minutes(iso) {
                    return Math.max(0, Math.floor((this.now - new Date(iso).getTime()) / 60000));
                },

                ageLabel(iso) {
                    const total = Math.max(0, Math.floor((this.now - new Date(iso).getTime()) / 1000));
                    const m = Math.floor(total / 60), s = total % 60;
                    return `${m}:${String(s).padStart(2, '0')}`;
                },

                ageClass(iso, status) {
                    if (status === 'ready') {
                        return { border: 'border-green-500', header: 'bg-green-600', text: 'text-green-600' };
                    }
                    const m = this.minutes(iso);
                    if (m < 5)  return { border: 'border-green-400', header: 'bg-green-500', text: 'text-green-600' };
                    if (m < 10) return { border: 'border-amber-400', header: 'bg-amber-500', text: 'text-amber-600' };
                    return { border: 'border-red-500 animate-pulse', header: 'bg-red-600', text: 'text-red-600' };
                },

                chime() {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const o = ctx.createOscillator();
                        const g = ctx.createGain();
                        o.type = 'sine';
                        o.frequency.value = 880;
                        g.gain.setValueAtTime(0.25, ctx.currentTime);
                        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
                        o.connect(g).connect(ctx.destination);
                        o.start();
                        o.stop(ctx.currentTime + 0.6);
                    } catch (e) { /* audio blocked until user interacts — fine */ }
                },
            };
        }
    </script>
</x-filament-panels::page>
