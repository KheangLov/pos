<?php

namespace App\Console\Commands;

use BladeUI\Icons\Factory;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Rebuilds blade-icons' manifest cache using a directory scan that is reliable
 * on this project's Docker bind mount.
 *
 * Why this exists: blade-icons' own IconsManifest::scanLocal() walks icon
 * directories with RecursiveDirectoryIterator, which silently returns only a
 * subset of entries for large directories on Docker Desktop's Windows bind
 * mount — 515 of 1288 heroicons, dropping everything alphabetically before
 * "o-cog". Any icon in the dropped range then fails to resolve and takes the
 * whole page down with "Unable to locate a class or view for component
 * [heroicon-o-bell-alert]". glob() and Symfony Finder read the same directory
 * correctly; only the iterator is affected.
 *
 * This only bites in local development, where the source tree is bind-mounted
 * (see the `.:/var/www/html` volume in compose.yaml). Production images copy
 * the source in and read it from the container's own filesystem, where the
 * iterator behaves, so `php artisan optimize` there produces a full manifest.
 *
 * Run this AFTER `php artisan optimize`: blade-icons hooks its own icons:cache
 * into optimize, so optimize will first write a truncated manifest that this
 * command then replaces.
 */
class CacheIcons extends Command
{
    protected $signature = 'app:icons-cache';

    protected $description = 'Rebuild the blade-icons manifest with a bind-mount-safe directory scan (run after `optimize`)';

    public function handle(Factory $factory, Filesystem $files): int
    {
        $manifest = [];
        $total = 0;

        foreach ($factory->all() as $name => $set) {
            $icons = [];

            foreach ($set['paths'] ?? [] as $path) {
                if (! is_dir($path)) {
                    continue;
                }

                $found = $this->scan($path);

                sort($found);

                if ($found !== []) {
                    $icons[$path] = $found;
                    $total += count($found);
                }
            }

            $manifest[$name] = $icons;
        }

        $path = app()->bootstrapPath('cache/blade-icons.php');

        $files->replace($path, '<?php return '.var_export($manifest, true).';');

        foreach ($manifest as $name => $paths) {
            $this->line(sprintf('  %s: %d icons', $name, array_sum(array_map('count', $paths))));
        }

        $this->info("Icon manifest cached ({$total} icons) → {$path}");

        return self::SUCCESS;
    }

    /**
     * Recursively collect icon names below $root, matching blade-icons'
     * naming: nested directories become dot-separated prefixes
     * (foo/bar.svg → "foo.bar"). Uses glob() rather than
     * RecursiveDirectoryIterator — see the class docblock.
     *
     * @return array<int, string>
     */
    private function scan(string $root, string $prefix = ''): array
    {
        $names = [];

        foreach (glob(rtrim($root, '/').'/*.svg') ?: [] as $file) {
            $names[] = $prefix.basename($file, '.svg');
        }

        foreach (glob(rtrim($root, '/').'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $names = [...$names, ...$this->scan($dir, $prefix.basename($dir).'.')];
        }

        return $names;
    }
}
