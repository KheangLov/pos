<x-filament-panels::page>
    <div class="flex flex-col gap-4">
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 flex flex-col h-[calc(100vh-14rem)]">
            <div class="flex-1 overflow-y-auto p-4 space-y-4" x-data x-init="$el.scrollTop = $el.scrollHeight" x-effect="$el.scrollTop = $el.scrollHeight">
                @if (empty($messages))
                    <div class="h-full flex flex-col items-center justify-center text-center gap-2 text-gray-400 dark:text-gray-500">
                        <x-heroicon-o-sparkles class="w-8 h-8" />
                        <p class="text-sm">Ask about today's sales, low stock, or your best sellers.</p>
                    </div>
                @endif

                @foreach ($messages as $message)
                    <div @class([
                        'flex',
                        'justify-end' => $message['role'] === 'user',
                        'justify-start' => $message['role'] !== 'user',
                    ])>
                        <div @class([
                            'max-w-2xl rounded-2xl px-4 py-2.5 text-sm whitespace-pre-wrap',
                            'bg-primary-600 text-white' => $message['role'] === 'user',
                            'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white' => $message['role'] === 'assistant',
                            'bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-900' => $message['role'] === 'error',
                        ])>
                            {{ $message['text'] }}
                        </div>
                    </div>
                @endforeach

                <div wire:loading wire:target="send" class="flex justify-start">
                    <div class="rounded-2xl px-4 py-2.5 bg-gray-100 dark:bg-gray-800">
                        <x-filament::loading-indicator class="w-4 h-4 text-gray-400" />
                    </div>
                </div>
            </div>

            <form wire:submit="send" class="border-t border-gray-200 dark:border-gray-800 p-3 flex items-center gap-2">
                <input
                    type="text"
                    wire:model="question"
                    placeholder="Ask a question about your business…"
                    autofocus
                    wire:loading.attr="disabled"
                    wire:target="send"
                    class="flex-1 px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm disabled:opacity-60"
                />
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="send"
                    class="shrink-0 w-11 h-11 flex items-center justify-center rounded-xl bg-primary-600 hover:bg-primary-500 text-white disabled:opacity-60 transition-colors">
                    <x-heroicon-o-paper-airplane class="w-5 h-5" />
                </button>
                @if (! empty($messages))
                    <button
                        type="button"
                        wire:click="clear"
                        title="Clear conversation"
                        class="shrink-0 w-11 h-11 flex items-center justify-center rounded-xl border border-gray-200 dark:border-gray-700 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                        <x-heroicon-o-trash class="w-5 h-5" />
                    </button>
                @endif
            </form>
        </div>
    </div>
</x-filament-panels::page>
