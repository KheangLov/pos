<x-filament-panels::page>
    <div class="flex flex-col gap-4">
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-4">
            <div class="flex-1">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search log messages…"
                    class="w-full px-3.5 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm"
                />
            </div>
            <select
                wire:model.live="level"
                class="px-3.5 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm"
            >
                <option value="">All levels</option>
                @foreach ($levels as $lvl)
                    <option value="{{ $lvl }}">{{ ucfirst($lvl) }}</option>
                @endforeach
            </select>
            <select
                wire:model.live="limit"
                class="px-3.5 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm"
            >
                @foreach ([50, 100, 200, 500] as $n)
                    <option value="{{ $n }}">Show {{ $n }}</option>
                @endforeach
            </select>
            <span class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">
                laravel.log — {{ $fileSize }}
            </span>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800 overflow-hidden">
            @forelse ($entries as $i => $entry)
                <div x-data="{ open: false }" class="p-4">
                    <button
                        type="button"
                        @click="open = ! open"
                        class="w-full flex items-start gap-3 text-left"
                    >
                        <span
                            @class([
                                'shrink-0 mt-0.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold uppercase',
                                'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => in_array($entry['level'], ['emergency', 'alert', 'critical', 'error']),
                                'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $entry['level'] === 'warning',
                                'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' => in_array($entry['level'], ['notice', 'info']),
                                'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' => $entry['level'] === 'debug',
                            ])
                        >
                            {{ $entry['level'] }}
                        </span>
                        <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500 mt-0.5 font-mono">{{ $entry['date'] }}</span>
                        <span class="flex-1 text-sm text-gray-800 dark:text-gray-200 break-all">{{ \Illuminate\Support\Str::limit($entry['message'], 200) }}</span>
                        @if ($entry['trace'] !== '')
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-gray-400 shrink-0 transition-transform" x-bind:class="open && 'rotate-180'">
                                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                            </svg>
                        @endif
                    </button>

                    @if ($entry['trace'] !== '')
                        <pre x-show="open" x-cloak class="mt-3 ml-2 p-3 rounded-lg bg-gray-50 dark:bg-gray-950 text-xs text-gray-600 dark:text-gray-400 overflow-x-auto whitespace-pre-wrap break-all">{{ $entry['trace'] }}</pre>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center text-sm text-gray-400 dark:text-gray-500">
                    No log entries match the current filters.
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
