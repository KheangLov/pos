<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class SystemLogs extends Page
{
    /**
     * Only ever read the tail of the file - the log can grow to many MB over
     * time and this page has no business loading the whole thing per request.
     */
    private const MAX_BYTES = 2 * 1024 * 1024;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'System Logs';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'System Logs';

    protected string $view = 'filament.pages.system-logs';

    public ?string $level = null;

    public string $search = '';

    public int $limit = 100;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
    }

    protected function getViewData(): array
    {
        return [
            'entries' => $this->entries(),
            'levels' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
            'fileSize' => $this->fileSizeForHumans(),
        ];
    }

    /**
     * @return array<int, array{date: string, env: string, level: string, message: string, trace: string}>
     */
    private function entries(): array
    {
        $path = storage_path('logs/laravel.log');

        if (! is_file($path)) {
            return [];
        }

        $size = filesize($path);
        $offset = max(0, $size - self::MAX_BYTES);

        $handle = fopen($path, 'r');
        fseek($handle, $offset);
        $chunk = fread($handle, $size - $offset);
        fclose($handle);

        // We likely started mid-line if we skipped the start of the file -
        // drop that partial first line rather than mis-parse it.
        if ($offset > 0 && ($newlinePos = strpos($chunk, "\n")) !== false) {
            $chunk = substr($chunk, $newlinePos + 1);
        }

        $entries = [];
        $current = null;

        foreach (explode("\n", $chunk) as $line) {
            if (preg_match('/^\[(?<date>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (?<env>\w+)\.(?<level>[A-Z]+): (?<message>.*)$/', $line, $m)) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $current = [
                    'date' => $m['date'],
                    'env' => $m['env'],
                    'level' => strtolower($m['level']),
                    'message' => $m['message'],
                    'trace' => '',
                ];
            } elseif ($current !== null && $line !== '') {
                $current['trace'] .= ($current['trace'] === '' ? '' : "\n").$line;
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        $entries = array_reverse($entries);

        if ($this->level) {
            $entries = array_values(array_filter($entries, fn ($e) => $e['level'] === $this->level));
        }

        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $entries = array_values(array_filter(
                $entries,
                fn ($e) => str_contains(mb_strtolower($e['message']), $needle) || str_contains(mb_strtolower($e['trace']), $needle)
            ));
        }

        return array_slice($entries, 0, $this->limit);
    }

    private function fileSizeForHumans(): string
    {
        $path = storage_path('logs/laravel.log');

        if (! is_file($path)) {
            return '0 B';
        }

        $bytes = filesize($path);
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
