<x-filament-panels::page>
    <div class="flex flex-col lg:flex-row gap-6 h-[calc(100vh-12rem)]" x-data="{
        cart: [],
        categories: ['All', 'Coffee', 'Tea', 'Pastries', 'Electronics'],
        activeCategory: 'All',
        products: [
            {id: 1, name: 'Espresso', price: 2.50, category: 'Coffee', image: 'https://images.unsplash.com/photo-1510591509098-f4fdc6d0ff04?auto=format&fit=crop&w=300&q=80'},
            {id: 2, name: 'Latte', price: 3.50, category: 'Coffee', image: 'https://images.unsplash.com/photo-1497935586351-b67a49e012bf?auto=format&fit=crop&w=300&q=80'},
            {id: 3, name: 'Green Tea', price: 2.00, category: 'Tea', image: 'https://images.unsplash.com/photo-1625895197185-efc01c050899?auto=format&fit=crop&w=300&q=80'},
            {id: 4, name: 'Croissant', price: 3.00, category: 'Pastries', image: 'https://images.unsplash.com/photo-1555507015-c22046ff175d?auto=format&fit=crop&w=300&q=80'},
            {id: 5, name: 'iPhone 15 Case', price: 25.00, category: 'Electronics', image: 'https://images.unsplash.com/photo-1603313011101-320f26a4f6f6?auto=format&fit=crop&w=300&q=80'}
        ],
        get filteredProducts() {
            if(this.activeCategory === 'All') return this.products;
            return this.products.filter(p => p.category === this.activeCategory);
        },
        addToCart(product) {
            let item = this.cart.find(i => i.id === product.id);
            if(item) {
                item.qty++;
            } else {
                this.cart.push({...product, qty: 1});
            }
        },
        removeFromCart(id) {
            this.cart = this.cart.filter(i => i.id !== id);
        },
        increment(id) {
            let item = this.cart.find(i => i.id === id);
            if(item) item.qty++;
        },
        decrement(id) {
            let item = this.cart.find(i => i.id === id);
            if(item && item.qty > 1) {
                item.qty--;
            } else {
                this.removeFromCart(id);
            }
        },
        get subtotal() {
            return this.cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        },
        get tax() {
            return this.subtotal * 0.10; // 10% tax
        },
        get total() {
            return this.subtotal + this.tax;
        }
    }">
        {{-- Left: Product Catalog --}}
        <div class="flex-1 flex flex-col bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
            {{-- Header/Categories --}}
            <div class="p-4 border-b border-gray-200 dark:border-gray-800 overflow-x-auto">
                <div class="flex gap-2">
                    <template x-for="cat in categories" :key="cat">
                        <button 
                            @click="activeCategory = cat"
                            :class="{'bg-primary-600 text-white': activeCategory === cat, 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700': activeCategory !== cat}"
                            class="px-5 py-2.5 rounded-full text-sm font-medium transition-colors whitespace-nowrap shadow-sm">
                            <span x-text="cat"></span>
                        </button>
                    </template>
                </div>
            </div>
            
            {{-- Product Grid --}}
            <div class="flex-1 p-4 overflow-y-auto">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    <template x-for="product in filteredProducts" :key="product.id">
                        <div @click="addToCart(product)" class="group cursor-pointer bg-gray-50 dark:bg-gray-800/50 rounded-2xl overflow-hidden hover:shadow-md transition-all duration-200 border border-transparent hover:border-primary-500/50 flex flex-col">
                            <div class="aspect-square bg-gray-200 dark:bg-gray-700 overflow-hidden relative">
                                <img :src="product.image" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            </div>
                            <div class="p-3 flex-1 flex flex-col justify-between">
                                <h3 class="font-semibold text-gray-900 dark:text-white line-clamp-2 leading-tight" x-text="product.name"></h3>
                                <p class="text-primary-600 dark:text-primary-400 font-bold mt-1">$<span x-text="product.price.toFixed(2)"></span></p>
                            </div>
                        </div>
                    </template>
                </div>
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
                    <template x-for="item in cart" :key="item.id">
                        <div class="flex items-start justify-between p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700/50">
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 dark:text-white" x-text="item.name"></h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">$<span x-text="item.price.toFixed(2)"></span></p>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <div class="flex items-center bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-0.5">
                                    <button @click="decrement(item.id)" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-colors">
                                        <x-heroicon-s-minus class="w-4 h-4" />
                                    </button>
                                    <span class="w-8 text-center font-semibold text-sm" x-text="item.qty"></span>
                                    <button @click="increment(item.id)" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-md transition-colors">
                                        <x-heroicon-s-plus class="w-4 h-4" />
                                    </button>
                                </div>
                                <div class="w-16 text-right font-bold text-gray-900 dark:text-white">
                                    $<span x-text="(item.price * item.qty).toFixed(2)"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Checkout Footer --}}
            <div class="p-5 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 space-y-3">
                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                    <span>Subtotal</span>
                    <span class="font-medium">$<span x-text="subtotal.toFixed(2)"></span></span>
                </div>
                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                    <span>Tax (10%)</span>
                    <span class="font-medium">$<span x-text="tax.toFixed(2)"></span></span>
                </div>
                <div class="flex justify-between text-lg font-bold text-gray-900 dark:text-white pt-2 border-t border-gray-200 dark:border-gray-700">
                    <span>Total</span>
                    <span>$<span x-text="total.toFixed(2)"></span></span>
                </div>
                
                <button 
                    :disabled="cart.length === 0"
                    class="w-full mt-4 py-3.5 px-4 bg-primary-600 hover:bg-primary-500 disabled:bg-gray-300 dark:disabled:bg-gray-700 disabled:cursor-not-allowed text-white font-bold rounded-xl shadow-lg shadow-primary-500/30 transition-all active:scale-95 flex items-center justify-center gap-2">
                    <x-heroicon-o-credit-card class="w-6 h-6" />
                    Checkout & Pay
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
