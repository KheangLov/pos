<x-filament-panels::page>
    @vite(['resources/js/app.js'])
    @if (! $shift)
        {{-- No open shift for this branch yet - block the terminal until one is opened --}}
        <div class="flex items-center justify-center h-[calc(100vh-12rem)]">
            <div class="w-full max-w-sm bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 p-6 text-center space-y-4"
                 x-data="{ openingAmount: '' }">
                <div class="w-14 h-14 mx-auto rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center">
                    <x-heroicon-o-lock-open class="w-7 h-7 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h2 class="font-bold text-lg text-gray-900 dark:text-white">No shift is open</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Count the starting cash in the drawer and open a shift to start taking orders.</p>
                </div>
                <div class="text-left">
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Opening float</label>
                    <div class="relative mt-1">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                        <input type="number" step="0.01" min="0" x-model="openingAmount" placeholder="0.00"
                               class="w-full pl-7 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm" />
                    </div>
                </div>
                <button
                    type="button"
                    @click="$wire.openShift(openingAmount || 0)"
                    wire:loading.attr="disabled"
                    wire:target="openShift"
                    class="w-full py-3 bg-primary-600 hover:bg-primary-500 disabled:opacity-60 text-white font-bold rounded-xl transition-colors flex items-center justify-center gap-2">
                    <x-filament::loading-indicator class="w-4 h-4" wire:loading wire:target="openShift" />
                    <span>Open Shift</span>
                </button>
            </div>
        </div>
    @else
    <div id="pos-terminal" class="relative flex flex-col lg:flex-row gap-6 h-[calc(100vh-12rem)]" x-data="{
        cart: [],
        method: 'cash',
        discounts: {{ \Illuminate\Support\Js::from($discounts->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'type' => $d->type, 'value' => (float) $d->value])) }},
        selectedDiscountId: null,
        processing: false,
        picker: null,
        kiosk: false,
        init() {
            document.addEventListener('fullscreenchange', () => {
                if (! document.fullscreenElement && this.kiosk) this.exitKiosk();
            });
            document.addEventListener('livewire:navigating', () => {
                if (this.kiosk) this.exitKiosk();
            }, { once: true });
        },
        toggleKiosk() {
            this.kiosk ? this.exitKiosk() : this.enterKiosk();
        },
        enterKiosk() {
            this.kiosk = true;
            document.documentElement.classList.add('pos-kiosk');
            document.documentElement.requestFullscreen?.().catch(() => {});
        },
        exitKiosk() {
            this.kiosk = false;
            document.documentElement.classList.remove('pos-kiosk');
            if (document.fullscreenElement) document.exitFullscreen?.();
        },
        checkout() {
            this.processing = true;
            this.$wire.checkout(this.cart, this.method, this.selectedDiscountId).finally(() => { this.processing = false; });
        },
        // Entry point for both the product grid and the barcode-scan flow:
        // products with modifier groups open the picker first, plain
        // products go straight into the cart.
        selectProduct(product) {
            if (product.modifierGroups && product.modifierGroups.length > 0) {
                this.openPicker(product);
            } else {
                this.addToCart(product);
            }
        },
        addToCart(product) {
            let modifiers = product.modifiers ?? [];
            let modifierKey = modifiers.map(m => m.id).sort().join(',');
            let key = product.id + '-' + (product.variant_id ?? '') + '-' + modifierKey;
            let item = this.cart.find(i => i.key === key);
            if(item) {
                item.qty++;
            } else {
                this.cart.push({...product, modifiers: modifiers, key: key, qty: 1});
            }
        },
        removeFromCart(key) {
            this.cart = this.cart.filter(i => i.key !== key);
        },
        increment(key) {
            let item = this.cart.find(i => i.key === key);
            if(item) item.qty++;
        },
        decrement(key) {
            let item = this.cart.find(i => i.key === key);
            if(item && item.qty > 1) {
                item.qty--;
            } else {
                this.removeFromCart(key);
            }
        },
        get subtotal() {
            return this.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        },
        get selectedDiscount() {
            return this.discounts.find(d => d.id === this.selectedDiscountId) ?? null;
        },
        get discountAmount() {
            if (! this.selectedDiscount) return 0;
            let raw = this.selectedDiscount.type === 'percentage'
                ? this.subtotal * (this.selectedDiscount.value / 100)
                : this.selectedDiscount.value;
            return Math.min(Math.max(raw, 0), this.subtotal);
        },
        get tax() {
            return (this.subtotal - this.discountAmount) * ({{ $taxRate }} / 100);
        },
        get total() {
            return this.subtotal - this.discountAmount + this.tax;
        },
        clearCart() {
            this.cart = [];
            this.selectedDiscountId = null;
        },
        openPicker(product) {
            this.picker = {
                product: product,
                selections: Object.fromEntries(product.modifierGroups.map(g => [g.id, []])),
            };
        },
        closePicker() {
            this.picker = null;
        },
        toggleModifier(group, modifierId) {
            let current = this.picker.selections[group.id];
            if (group.selection_type === 'single') {
                this.picker.selections[group.id] = current.includes(modifierId) ? [] : [modifierId];
                return;
            }
            if (current.includes(modifierId)) {
                this.picker.selections[group.id] = current.filter(id => id !== modifierId);
            } else if (! group.max_selections || current.length < group.max_selections) {
                this.picker.selections[group.id] = [...current, modifierId];
            }
        },
        get pickerValid() {
            if (! this.picker) return false;
            return this.picker.product.modifierGroups.every(g => {
                let count = this.picker.selections[g.id].length;
                return count >= (g.min_selections ?? 0) && (! g.max_selections || count <= g.max_selections);
            });
        },
        pickerSelectedModifiers() {
            let modifiers = [];
            this.picker.product.modifierGroups.forEach(g => {
                g.modifiers.forEach(m => {
                    if (this.picker.selections[g.id].includes(m.id)) modifiers.push(m);
                });
            });
            return modifiers;
        },
        get pickerTotal() {
            if (! this.picker) return 0;
            return this.picker.product.price + this.pickerSelectedModifiers().reduce((sum, m) => sum + m.price, 0);
        },
        confirmPicker() {
            if (! this.pickerValid) return;
            let modifiers = this.pickerSelectedModifiers();
            this.addToCart({...this.picker.product, price: this.pickerTotal, modifiers: modifiers});
            this.closePicker();
        },
    }"
    @clear-cart.window="clearCart()"
    @pos-add-product.window="selectProduct($event.detail.product)">
        {{-- Kiosk toggle --}}
        <button
            type="button"
            @click="toggleKiosk()"
            :title="kiosk ? 'Exit fullscreen' : 'Enter fullscreen'"
            class="absolute top-0 right-0 z-10 w-9 h-9 flex items-center justify-center rounded-lg bg-white/90 dark:bg-gray-900/90 border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 shadow-sm">
            <x-heroicon-o-x-mark class="w-5 h-5" x-show="kiosk" x-cloak />
            <x-heroicon-o-viewfinder-circle class="w-5 h-5" x-show="!kiosk" />
        </button>

        {{-- Left: Product Catalog --}}
        <div class="flex-1 flex flex-col bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
            {{-- Search --}}
            <div class="p-4 border-b border-gray-200 dark:border-gray-800 space-y-3">
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        type="search"
                        wire:model.live.debounce.250ms="search"
                        wire:keydown.enter.prevent="addFirstMatch"
                        placeholder="Search products, scan barcode or SKU…"
                        autofocus
                        class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm"
                    />
                    <div wire:loading.flex wire:target="search, addFirstMatch" class="absolute right-3.5 top-1/2 -translate-y-1/2 items-center">
                        <x-filament::loading-indicator class="w-4 h-4 text-primary-500" />
                    </div>
                </div>

                {{-- Categories --}}
                <div class="flex gap-2 overflow-x-auto pb-1">
                    <button
                        wire:click="setCategory(null)"
                        wire:loading.attr="disabled"
                        wire:target="setCategory(null)"
                        @class([
                            'px-5 py-2 rounded-full text-sm font-medium transition-colors whitespace-nowrap shadow-sm disabled:opacity-50 disabled:cursor-wait',
                            'bg-primary-600 text-white' => $activeCategory === null,
                            'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' => $activeCategory !== null,
                        ])>
                        All
                    </button>
                    @foreach ($categories as $category)
                        <button
                            wire:click="setCategory({{ $category->id }})"
                            wire:loading.attr="disabled"
                            wire:target="setCategory({{ $category->id }})"
                            @class([
                                'px-5 py-2 rounded-full text-sm font-medium transition-colors whitespace-nowrap shadow-sm disabled:opacity-50 disabled:cursor-wait',
                                'bg-primary-600 text-white' => $activeCategory === $category->id,
                                'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' => $activeCategory !== $category->id,
                            ])>
                            {{ $category->name }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Product Grid --}}
            <div class="flex-1 p-4 overflow-y-auto" wire:loading.class="opacity-50" wire:target="search, addFirstMatch, setCategory">
                @if ($products->isEmpty())
                    <div class="h-full flex flex-col items-center justify-center text-gray-400 dark:text-gray-500 space-y-3">
                        <x-heroicon-o-magnifying-glass class="w-14 h-14 opacity-20" />
                        <p class="text-sm font-medium">No products match “{{ $search }}”.</p>
                        <button wire:click="$set('search', '')" class="text-primary-600 dark:text-primary-400 text-sm font-semibold hover:underline">
                            Clear search
                        </button>
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                        @foreach ($products as $product)
                            @php
                                $defaultVariant = $product->variants->firstWhere('additional_price', 0.00) ?? $product->variants->first();
                                $displayPrice = (float) $product->base_price + (float) ($defaultVariant->additional_price ?? 0);
                                $modifierGroups = $this->modifierGroupsPayload($product);
                                $productPayload = \Illuminate\Support\Js::from([
                                    'id' => $product->id,
                                    'variant_id' => $defaultVariant?->id,
                                    'name' => $product->name.($defaultVariant ? ' ('.$defaultVariant->name.')' : ''),
                                    'price' => round($displayPrice, 2),
                                    'modifierGroups' => $modifierGroups,
                                ]);
                            @endphp
                            <button
                                type="button"
                                @click="selectProduct({{ $productPayload }})"
                                class="group cursor-pointer bg-gray-50 dark:bg-gray-800/50 rounded-2xl overflow-hidden hover:shadow-md transition-all duration-200 border border-transparent hover:border-primary-500/50 flex flex-col text-left">
                                <div class="aspect-square overflow-hidden relative bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/40 dark:to-primary-800/40 flex items-center justify-center">
                                    <img src="{{ $product->imageThumbUrl() ?? asset('images/product-placeholder.svg') }}" alt="{{ $product->name }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                                    @if ($product->variants->isNotEmpty())
                                        <span class="absolute top-2 right-2 bg-white/80 dark:bg-gray-900/80 text-[10px] font-bold text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded-full">
                                            {{ $product->variants->count() }} options
                                        </span>
                                    @endif
                                    @if ($modifierGroups !== [])
                                        <span class="absolute top-2 left-2 bg-primary-600/90 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                                            Customizable
                                        </span>
                                    @endif
                                </div>
                                <div class="p-3 flex-1 flex flex-col justify-between">
                                    <h3 class="font-semibold text-gray-900 dark:text-white line-clamp-2 leading-tight text-sm">{{ $product->name }}</h3>
                                    <div class="flex items-center justify-between mt-1">
                                        <p class="text-primary-600 dark:text-primary-400 font-bold">${{ number_format($displayPrice, 2) }}</p>
                                        <span class="text-[10px] text-gray-400 uppercase tracking-wide">{{ $product->sku }}</span>
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Right: Cart / Order Summary --}}
        <div class="w-full lg:w-[400px] xl:w-[450px] flex flex-col bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden shrink-0">
            {{-- Cart Header --}}
            <div class="p-4 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center bg-gray-50 dark:bg-gray-800/50">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-shopping-cart class="w-6 h-6 text-primary-500" />
                    Current Order
                </h2>
                <span class="bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-400 text-xs font-bold px-2.5 py-1 rounded-full" x-text="cart.length + ' Items'"></span>
            </div>

            {{-- Cart Items --}}
            <div class="flex-1 p-4 overflow-y-auto">
                <template x-if="cart.length === 0">
                    <div class="h-full flex flex-col items-center justify-center text-gray-400 dark:text-gray-500 space-y-4">
                        <x-heroicon-o-inbox class="w-16 h-16 opacity-20" />
                        <p class="text-sm font-medium">Your cart is empty.</p>
                    </div>
                </template>

                <div class="space-y-3">
                    <template x-for="item in cart" :key="item.key">
                        <div class="flex items-start justify-between p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700/50">
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 dark:text-white text-sm" x-text="item.name"></h4>
                                <template x-if="item.modifiers && item.modifiers.length > 0">
                                    <p class="text-xs text-primary-600 dark:text-primary-400" x-text="item.modifiers.map(m => m.name).join(', ')"></p>
                                </template>
                                <p class="text-sm text-gray-500 dark:text-gray-400">$<span x-text="item.price.toFixed(2)"></span></p>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="flex items-center bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-0.5">
                                    <button @click="decrement(item.key)" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-colors">
                                        <x-heroicon-s-minus class="w-4 h-4" />
                                    </button>
                                    <span class="w-8 text-center font-semibold text-sm" x-text="item.qty"></span>
                                    <button @click="increment(item.key)" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-md transition-colors">
                                        <x-heroicon-s-plus class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="w-16 text-right font-bold text-gray-900 dark:text-white text-sm">
                                    $<span x-text="(item.price * item.qty).toFixed(2)"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Checkout Footer --}}
            <div class="p-5 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 space-y-3">
                @if ($discounts->isNotEmpty())
                    <div>
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Discount</label>
                        <select x-model.number="selectedDiscountId" class="w-full mt-1 py-2 px-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option :value="null">No discount</option>
                            <template x-for="d in discounts" :key="d.id">
                                <option :value="d.id" x-text="d.name + ' (' + (d.type === 'percentage' ? d.value + '%' : '$' + d.value.toFixed(2)) + ')'"></option>
                            </template>
                        </select>
                    </div>
                @endif
                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                    <span>Subtotal</span>
                    <span class="font-medium">$<span x-text="subtotal.toFixed(2)"></span></span>
                </div>
                <div class="flex justify-between text-sm text-primary-600 dark:text-primary-400" x-show="discountAmount > 0" x-cloak>
                    <span x-text="'Discount' + (selectedDiscount ? ' · ' + selectedDiscount.name : '')"></span>
                    <span class="font-medium">-$<span x-text="discountAmount.toFixed(2)"></span></span>
                </div>
                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                    <span>Tax ({{ rtrim(rtrim(number_format($taxRate, 2), '0'), '.') }}%)</span>
                    <span class="font-medium">$<span x-text="tax.toFixed(2)"></span></span>
                </div>
                <div class="flex justify-between text-lg font-bold text-gray-900 dark:text-white pt-2 border-t border-gray-200 dark:border-gray-700">
                    <span>Total</span>
                    <span>$<span x-text="total.toFixed(2)"></span></span>
                </div>

                {{-- Payment method --}}
                <div class="grid grid-cols-3 gap-2 pt-1">
                    <template x-for="m in ['cash', 'card', 'qr']" :key="m">
                        <button
                            @click="method = m"
                            :class="method === m
                                ? 'bg-primary-600 text-white shadow'
                                : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700'"
                            class="py-2 rounded-lg text-xs font-bold uppercase tracking-wide transition-colors"
                            x-text="m"></button>
                    </template>
                </div>

                <button
                    @click="checkout()"
                    :disabled="cart.length === 0 || processing"
                    class="w-full mt-2 py-3.5 px-4 bg-primary-600 hover:bg-primary-500 disabled:bg-gray-300 dark:disabled:bg-gray-700 disabled:cursor-wait text-white font-bold rounded-xl shadow-lg shadow-primary-500/30 transition-all active:scale-95 flex items-center justify-center gap-2">
                    <x-heroicon-o-credit-card class="w-6 h-6" x-show="!processing" />
                    <x-filament::loading-indicator class="w-6 h-6" x-show="processing" x-cloak />
                    <span x-text="processing ? 'Processing…' : 'Checkout & Pay'"></span>
                </button>
            </div>
        </div>

        {{-- Modifier picker --}}
        <div x-show="picker" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
            <div
                class="absolute inset-0 bg-gray-950/60 backdrop-blur-[2px]"
                @click="closePicker()"
                x-transition:enter="transition-opacity ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"></div>
            <div
                class="relative bg-white dark:bg-gray-900 rounded-t-2xl sm:rounded-2xl shadow-2xl w-full sm:max-w-md max-h-[90vh] sm:max-h-[85vh] overflow-hidden flex flex-col"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95">
                <template x-if="picker">
                    <div class="flex flex-col max-h-[90vh] sm:max-h-[85vh]">
                        {{-- Header --}}
                        <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-start gap-3 shrink-0">
                            <div>
                                <h3 class="font-bold text-gray-900 dark:text-white leading-tight" x-text="picker.product.name"></h3>
                                <p class="text-xs text-gray-400 mt-0.5">Base price $<span x-text="picker.product.price.toFixed(2)"></span></p>
                            </div>
                            <button type="button" @click="closePicker()" class="shrink-0 w-8 h-8 -mt-1 -mr-1 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-gray-200 dark:hover:bg-gray-800 transition-colors">
                                <x-heroicon-o-x-mark class="w-5 h-5" />
                            </button>
                        </div>

                        {{-- Groups --}}
                        <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-for="group in picker.product.modifierGroups" :key="group.id">
                                <div class="p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="font-semibold text-sm text-gray-900 dark:text-white" x-text="group.name"></h4>
                                        <span
                                            class="text-[11px] font-medium px-2 py-0.5 rounded-full"
                                            :class="picker.selections[group.id].length >= (group.min_selections || 0)
                                                ? 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
                                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400'"
                                            x-text="group.min_selections > 0
                                                ? 'Required · ' + (group.selection_type === 'single' ? 'choose 1' : ('choose ' + group.min_selections + (group.max_selections && group.max_selections !== group.min_selections ? '-' + group.max_selections : '')))
                                                : (group.selection_type === 'single' ? 'Optional · choose 1' : (group.max_selections ? 'Optional · up to ' + group.max_selections : 'Optional'))"></span>
                                    </div>
                                    <div class="space-y-1.5">
                                        <template x-for="modifier in group.modifiers" :key="modifier.id">
                                            <button
                                                type="button"
                                                @click="toggleModifier(group, modifier.id)"
                                                :class="picker.selections[group.id].includes(modifier.id)
                                                    ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/30'
                                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'"
                                                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg border text-left transition-colors">
                                                {{-- Radio (single-select) / checkbox (multi-select) indicator --}}
                                                <span
                                                    :class="[
                                                        picker.selections[group.id].includes(modifier.id) ? 'border-primary-500 bg-primary-500' : 'border-gray-300 dark:border-gray-600',
                                                        group.selection_type === 'single' ? 'rounded-full' : 'rounded-[5px]'
                                                    ]"
                                                    class="shrink-0 w-4.5 h-4.5 border-2 flex items-center justify-center transition-colors">
                                                    <x-heroicon-s-check class="w-3 h-3 text-white" x-show="picker.selections[group.id].includes(modifier.id)" x-cloak />
                                                </span>
                                                <span class="flex-1 text-sm font-medium text-gray-800 dark:text-gray-100" x-text="modifier.name"></span>
                                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 shrink-0"
                                                      x-text="modifier.price > 0 ? ('+$' + modifier.price.toFixed(2)) : 'Free'"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Footer --}}
                        <div class="p-4 border-t border-gray-100 dark:border-gray-800 shrink-0 space-y-2">
                            <p x-show="!pickerValid" x-cloak class="text-xs text-amber-600 dark:text-amber-400 text-center font-medium">
                                Please complete the required selections above
                            </p>
                            <button
                                type="button"
                                @click="confirmPicker()"
                                :disabled="!pickerValid"
                                class="w-full py-3.5 px-4 bg-primary-600 hover:bg-primary-500 disabled:bg-gray-200 dark:disabled:bg-gray-800 disabled:text-gray-400 dark:disabled:text-gray-600 disabled:cursor-not-allowed text-white font-bold rounded-xl shadow-lg shadow-primary-500/20 disabled:shadow-none transition-all active:scale-95 flex items-center justify-center gap-2">
                                <x-heroicon-o-shopping-cart class="w-5 h-5" />
                                <span>Add to Cart</span>
                                <span class="opacity-80">· $<span x-text="pickerTotal.toFixed(2)"></span></span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
    @endif
</x-filament-panels::page>
