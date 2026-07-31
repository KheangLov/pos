<div class="min-h-screen bg-stone-50 flex flex-col" x-data="{ mobileCartOpen: false }">
    <!-- Header -->
    <header class="bg-white/95 backdrop-blur-sm shadow-sm sticky top-0 z-40 border-b border-stone-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-3.5">
                <div class="flex items-center min-w-0">
                    @php $companyLogo = $table->floorPlan->branch->company->logoUrl(); @endphp
                    <img src="{{ $companyLogo ?? asset('images/company-placeholder.svg') }}" alt="{{ $table->floorPlan->branch->company->name }}" class="w-11 h-11 rounded-full object-cover shadow-sm ring-2 ring-white shrink-0" />
                    <div class="ml-3 min-w-0">
                        <h1 class="text-lg font-bold text-stone-900 leading-tight truncate">{{ $table->floorPlan->branch->company->name ?? 'Menu' }}</h1>
                        <p class="text-xs text-stone-500 font-medium">Table {{ $table->name }} · Scan &amp; order</p>
                    </div>
                </div>
                <button
                    @click="mobileCartOpen = true"
                    class="relative shrink-0 p-2.5 text-stone-500 hover:text-amber-600 hover:bg-amber-50 rounded-full transition-colors">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    @if(count($cart) > 0)
                        <span class="absolute top-0.5 right-0.5 inline-flex items-center justify-center w-5 h-5 text-[11px] font-bold text-white bg-amber-600 rounded-full ring-2 ring-white">{{ count($cart) }}</span>
                    @endif
                </button>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full flex gap-8 pb-28 lg:pb-8">
        <!-- Categories & Products -->
        <div class="w-full lg:w-2/3">
            <!-- Search -->
            <div class="relative mb-4">
                <svg class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-stone-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search the menu…"
                    class="w-full pl-12 pr-4 py-3.5 rounded-2xl border border-stone-200 bg-white shadow-sm text-stone-900 placeholder-stone-400 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                />
            </div>

            <!-- Categories -->
            <div class="flex overflow-x-auto pb-4 gap-2.5 hide-scroll-bar mb-2">
                <button wire:click="selectCategory(null)" class="flex-shrink-0 px-5 py-2.5 rounded-full text-sm font-semibold transition-all {{ is_null($selectedCategory) ? 'bg-amber-600 text-white shadow-md shadow-amber-600/20' : 'bg-white text-stone-600 border border-stone-200 hover:border-stone-300' }}">
                    All Items
                </button>
                @foreach($categories as $category)
                    <button wire:click="selectCategory({{ $category->id }})" class="flex-shrink-0 px-5 py-2.5 rounded-full text-sm font-semibold transition-all {{ $selectedCategory == $category->id ? 'bg-amber-600 text-white shadow-md shadow-amber-600/20' : 'bg-white text-stone-600 border border-stone-200 hover:border-stone-300' }}">
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>

            <!-- Products Grid -->
            @if ($products->isEmpty())
                <div class="bg-white rounded-2xl border border-stone-100 p-12 text-center">
                    <p class="text-stone-500 font-medium">Nothing matches "{{ $search }}".</p>
                    <button wire:click="$set('search', '')" class="mt-2 text-amber-600 font-semibold text-sm hover:underline">Clear search</button>
                </div>
            @endif
            <div class="grid grid-cols-2 sm:grid-cols-2 gap-4 sm:gap-5">
                @foreach($products as $product)
                    @php $hasModifiers = $product->modifierGroups->where('is_active', true)->isNotEmpty(); @endphp
                    <div class="bg-white rounded-3xl shadow-sm border border-stone-100 overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 flex flex-col">
                        <div class="aspect-square bg-stone-100 relative overflow-hidden group">
                            <img src="{{ $product->imageThumbUrl() ?? asset('images/product-placeholder.svg') }}" alt="{{ $product->name }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                            @if ($hasModifiers)
                                <div class="absolute top-2 left-2 bg-stone-900/80 backdrop-blur-sm text-white text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-full shadow-sm">
                                    Customizable
                                </div>
                            @endif
                        </div>
                        <div class="p-3.5 sm:p-4 flex-grow flex flex-col justify-between">
                            <div>
                                <h3 class="text-sm sm:text-base font-bold text-stone-900 leading-snug line-clamp-2 mb-1">{{ $product->name }}</h3>
                                <p class="hidden sm:block text-xs text-stone-500 line-clamp-2 mb-2">{{ $product->description ?? 'Freshly prepared.' }}</p>
                            </div>
                            <div class="flex items-center justify-between gap-2 mt-1">
                                <span class="text-amber-700 font-extrabold text-sm sm:text-base">${{ number_format($product->base_price, 2) }}</span>
                                <button
                                    wire:click="selectProduct({{ $product->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="selectProduct({{ $product->id }})"
                                    class="shrink-0 flex items-center gap-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 font-bold text-xs sm:text-sm py-2 px-3 sm:px-3.5 rounded-full transition-colors disabled:opacity-60">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                    <span>{{ $hasModifiers ? 'Customize' : 'Add' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Desktop Cart Sidebar -->
        <div class="hidden lg:block w-1/3">
            <div class="bg-white rounded-3xl shadow-sm border border-stone-100 p-6 sticky top-24">
                <h2 class="text-lg font-bold text-stone-900 mb-5 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    Your Order
                </h2>

                @if(empty($cart))
                    <div class="text-center py-10">
                        <div class="bg-stone-50 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-9 h-9 text-stone-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <p class="text-stone-500 font-medium">Your order is empty</p>
                        <p class="text-sm text-stone-400 mt-1">Add items to get started</p>
                    </div>
                @else
                    <div class="space-y-3 max-h-96 overflow-y-auto pr-1 mb-5">
                        @foreach($cart as $item)
                            <div class="flex justify-between items-center gap-2">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-bold text-stone-900 truncate">{{ $item['name'] }}</h4>
                                    @if (! empty($item['modifiers']))
                                        <p class="text-xs text-amber-700 truncate">{{ collect($item['modifiers'])->pluck('name')->implode(', ') }}</p>
                                    @endif
                                    <p class="text-xs text-stone-500">${{ number_format($item['price'], 2) }}</p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <div class="flex items-center bg-stone-100 rounded-lg p-1">
                                        <button wire:click="decrementCartItem('{{ $item['key'] }}')" class="w-7 h-7 flex items-center justify-center text-stone-600 hover:bg-white rounded hover:shadow-sm transition-all">−</button>
                                        <span class="w-7 text-center text-sm font-semibold">{{ $item['quantity'] }}</span>
                                        <button wire:click="incrementCartItem('{{ $item['key'] }}')" class="w-7 h-7 flex items-center justify-center text-stone-600 hover:bg-white rounded hover:shadow-sm transition-all">+</button>
                                    </div>
                                    <span class="font-bold text-stone-900 w-14 text-right text-sm">${{ number_format($item['price'] * $item['quantity'], 2) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-stone-100 pt-4 mb-5">
                        <div class="flex justify-between text-xl font-bold text-stone-900">
                            <p>Total</p>
                            <p>${{ number_format($total, 2) }}</p>
                        </div>
                    </div>

                    <button wire:click="checkout" wire:loading.attr="disabled" wire:target="checkout" class="w-full bg-amber-600 hover:bg-amber-500 disabled:opacity-60 text-white font-bold py-3.5 px-4 rounded-2xl shadow-lg shadow-amber-600/20 transition-all flex justify-center items-center gap-2">
                        <span>Place Order</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                @endif
            </div>
        </div>
    </main>

    <!-- Sticky mobile "view order" bar -->
    @if (! empty($cart))
        <button
            @click="mobileCartOpen = true"
            class="lg:hidden fixed bottom-4 inset-x-4 z-30 bg-stone-900 text-white rounded-2xl shadow-xl px-5 py-4 flex items-center justify-between">
            <span class="flex items-center gap-2 font-semibold text-sm">
                <span class="bg-amber-500 text-stone-900 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold">{{ count($cart) }}</span>
                View Order
            </span>
            <span class="font-bold">${{ number_format($total, 2) }}</span>
        </button>
    @endif

    <!-- Mobile Cart Drawer -->
    <div x-show="mobileCartOpen" x-cloak class="lg:hidden fixed inset-0 z-50 flex items-end">
        <div class="absolute inset-0 bg-stone-900/50 backdrop-blur-sm"
             @click="mobileCartOpen = false"
             x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
        <div class="relative w-full bg-white rounded-t-3xl shadow-2xl max-h-[85vh] flex flex-col"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
            <div class="p-4 border-b border-stone-100 flex justify-between items-center shrink-0">
                <h2 class="text-lg font-bold text-stone-900">Your Order</h2>
                <button @click="mobileCartOpen = false" class="p-2 text-stone-400 hover:text-stone-600 bg-stone-100 rounded-full">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="p-4 overflow-y-auto flex-1">
                @if(empty($cart))
                    <div class="text-center py-8">
                        <p class="text-stone-500">Your order is empty</p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($cart as $item)
                            <div class="flex justify-between items-center gap-2">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-bold text-stone-900 truncate">{{ $item['name'] }}</h4>
                                    @if (! empty($item['modifiers']))
                                        <p class="text-xs text-amber-700 truncate">{{ collect($item['modifiers'])->pluck('name')->implode(', ') }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <div class="flex items-center bg-stone-100 rounded-lg p-1">
                                        <button wire:click="decrementCartItem('{{ $item['key'] }}')" class="w-7 h-7 flex items-center justify-center text-stone-600">−</button>
                                        <span class="w-7 text-center text-sm font-semibold">{{ $item['quantity'] }}</span>
                                        <button wire:click="incrementCartItem('{{ $item['key'] }}')" class="w-7 h-7 flex items-center justify-center text-stone-600">+</button>
                                    </div>
                                    <span class="font-bold text-stone-900 w-14 text-right text-sm">${{ number_format($item['price'] * $item['quantity'], 2) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @if(!empty($cart))
                <div class="p-4 border-t border-stone-100 bg-stone-50 rounded-t-3xl shrink-0">
                    <div class="flex justify-between text-xl font-bold text-stone-900 mb-4">
                        <p>Total</p>
                        <p>${{ number_format($total, 2) }}</p>
                    </div>
                    <button wire:click="checkout" wire:loading.attr="disabled" wire:target="checkout" class="w-full bg-amber-600 hover:bg-amber-500 disabled:opacity-60 text-white font-bold py-4 px-4 rounded-2xl shadow-lg flex justify-center items-center gap-2">
                        <span>Place Order</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Modifier picker --}}
    @if ($pickingProductId)
        @php $pickingProduct = \App\Models\Product::find($pickingProductId); @endphp
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
            <div class="absolute inset-0 bg-stone-900/60 backdrop-blur-[2px]" wire:click="closePicker"></div>
            <div class="relative bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl w-full sm:max-w-md max-h-[90vh] sm:max-h-[85vh] overflow-hidden flex flex-col">
                <div class="p-4 border-b border-stone-100 flex justify-between items-start gap-3 shrink-0">
                    <div>
                        <h3 class="font-bold text-stone-900 leading-tight">{{ $pickingProduct?->name }}</h3>
                        <p class="text-xs text-stone-400 mt-0.5">Base price ${{ number_format($pickingProduct?->base_price ?? 0, 2) }}</p>
                    </div>
                    <button wire:click="closePicker" class="shrink-0 w-8 h-8 -mt-1 -mr-1 flex items-center justify-center rounded-full text-stone-400 hover:text-stone-600 hover:bg-stone-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto divide-y divide-stone-100">
                    @foreach ($pickingModifierGroups as $group)
                        @php $selectedInGroup = $pickerSelections[$group['id']] ?? []; @endphp
                        <div class="p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-semibold text-sm text-stone-900">{{ $group['name'] }}</h4>
                                <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ count($selectedInGroup) >= ($group['min_selections'] ?? 0) ? 'bg-stone-100 text-stone-500' : 'bg-amber-100 text-amber-700' }}">
                                    @if ($group['min_selections'] > 0)
                                        Required · {{ $group['selection_type'] === 'single' ? 'choose 1' : 'choose '.$group['min_selections'].($group['max_selections'] && $group['max_selections'] !== $group['min_selections'] ? '-'.$group['max_selections'] : '') }}
                                    @else
                                        {{ $group['selection_type'] === 'single' ? 'Optional · choose 1' : ($group['max_selections'] ? 'Optional · up to '.$group['max_selections'] : 'Optional') }}
                                    @endif
                                </span>
                            </div>
                            <div class="space-y-1.5">
                                @foreach ($group['modifiers'] as $modifier)
                                    @php $isSelected = in_array($modifier['id'], $selectedInGroup); @endphp
                                    <button
                                        type="button"
                                        wire:click="toggleModifierSelection({{ $group['id'] }}, {{ $modifier['id'] }})"
                                        wire:key="modifier-{{ $modifier['id'] }}"
                                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg border text-left transition-colors {{ $isSelected ? 'border-amber-500 bg-amber-50' : 'border-stone-200 hover:border-stone-300' }}">
                                        <span class="shrink-0 w-4.5 h-4.5 border-2 flex items-center justify-center transition-colors {{ $isSelected ? 'border-amber-500 bg-amber-500' : 'border-stone-300' }} {{ $group['selection_type'] === 'single' ? 'rounded-full' : 'rounded-[5px]' }}">
                                            @if ($isSelected)
                                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                            @endif
                                        </span>
                                        <span class="flex-1 text-sm font-medium text-stone-800">{{ $modifier['name'] }}</span>
                                        <span class="text-xs font-semibold text-stone-500 shrink-0">{{ $modifier['price'] > 0 ? '+$'.number_format($modifier['price'], 2) : 'Free' }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="p-4 border-t border-stone-100 shrink-0 space-y-2">
                    @if (! $this->pickerValid)
                        <p class="text-xs text-amber-600 text-center font-medium">Please complete the required selections above</p>
                    @endif
                    <button
                        wire:click="confirmPicker"
                        wire:loading.attr="disabled"
                        wire:target="confirmPicker"
                        @if (! $this->pickerValid) disabled @endif
                        class="w-full py-3.5 px-4 bg-amber-600 hover:bg-amber-500 disabled:bg-stone-200 disabled:text-stone-400 disabled:cursor-not-allowed text-white font-bold rounded-xl shadow-lg shadow-amber-600/20 disabled:shadow-none transition-all flex items-center justify-center gap-2">
                        <span>Add to Order</span>
                        <span class="opacity-80">· ${{ number_format($this->pickerTotal, 2) }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <style>
        .hide-scroll-bar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .hide-scroll-bar::-webkit-scrollbar {
            display: none;
        }
    </style>
</div>
