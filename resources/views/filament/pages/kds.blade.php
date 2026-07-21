<x-filament-panels::page>
    <div class="h-[calc(100vh-12rem)]" x-data="{
        orders: [
            { id: 101, table: 'Table 4', time: '12:05 PM', items: [{name: 'Espresso', qty: 2}, {name: 'Croissant', qty: 1}], status: 'pending' },
            { id: 102, table: 'Takeaway', time: '12:10 PM', items: [{name: 'Latte', qty: 1}], status: 'preparing' },
            { id: 103, table: 'Table 2', time: '12:15 PM', items: [{name: 'Green Tea', qty: 1}, {name: 'Espresso', qty: 1}], status: 'pending' }
        ],
        setStatus(id, status) {
            let order = this.orders.find(o => o.id === id);
            if(order) order.status = status;
        }
    }">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 h-full overflow-y-auto custom-scrollbar p-2">
            
            <template x-for="order in orders.filter(o => o.status !== 'ready')" :key="order.id">
                <div class="bg-white dark:bg-gray-900 border-2 rounded-2xl flex flex-col overflow-hidden shadow-sm transition-all"
                     :class="order.status === 'pending' ? 'border-amber-400' : 'border-primary-500'">
                    
                    {{-- Header --}}
                    <div class="p-4 flex justify-between items-center text-white"
                         :class="order.status === 'pending' ? 'bg-amber-400' : 'bg-primary-500'">
                        <div class="font-bold text-lg">
                            Order #<span x-text="order.id"></span>
                        </div>
                        <div class="font-bold bg-black/20 px-2 py-1 rounded-md text-sm" x-text="order.table"></div>
                    </div>
                    
                    <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800 text-xs font-semibold text-gray-500 flex justify-between items-center border-b border-gray-100 dark:border-gray-700">
                        <span>Time: <span x-text="order.time"></span></span>
                        <span class="px-2 py-0.5 rounded-full uppercase tracking-wider"
                              :class="order.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-primary-100 text-primary-700'"
                              x-text="order.status"></span>
                    </div>

                    {{-- Items --}}
                    <div class="flex-1 p-4 space-y-3 overflow-y-auto">
                        <template x-for="item in order.items">
                            <div class="flex justify-between items-center pb-2 border-b border-gray-100 dark:border-gray-800 last:border-0 last:pb-0">
                                <span class="font-medium text-gray-900 dark:text-gray-100 text-lg" x-text="item.name"></span>
                                <span class="font-bold text-gray-500 bg-gray-100 dark:bg-gray-800 px-3 py-1 rounded-lg text-lg">x<span x-text="item.qty"></span></span>
                            </div>
                        </template>
                    </div>

                    {{-- Actions --}}
                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-800">
                        <template x-if="order.status === 'pending'">
                            <button @click="setStatus(order.id, 'preparing')" class="w-full py-3 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl shadow-sm transition-colors text-lg">
                                Start Preparing
                            </button>
                        </template>
                        <template x-if="order.status === 'preparing'">
                            <button @click="setStatus(order.id, 'ready')" class="w-full py-3 bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-xl shadow-sm transition-colors text-lg">
                                Mark as Ready
                            </button>
                        </template>
                    </div>
                </div>
            </template>

        </div>
    </div>
</x-filament-panels::page>
