<?php

namespace App\Console\Commands;

use App\Services\LicenseManager;
use Illuminate\Console\Command;

class LicenseInstall extends Command
{
    protected $signature = 'license:install {key? : The licence key supplied by the vendor}';

    protected $description = 'Install a licence key on this system';

    public function handle(LicenseManager $licenses): int
    {
        $key = (string) ($this->argument('key') ?: $this->ask('Paste the licence key'));

        try {
            $license = $licenses->install($key);
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());
            $this->newLine();
            $this->line('Check the key was copied in full, including the leading <options=bold>OMNIPOS1.</> part.');

            return self::FAILURE;
        }

        $this->components->info('Licence installed.');
        $this->components->twoColumnDetail('Customer', $license->customer);
        $this->components->twoColumnDetail('Expires', $license->expiresAt?->toFormattedDateString() ?? 'never');

        if (! $license->matchesMachine($licenses->fingerprint())) {
            $this->newLine();
            $this->components->warn('This key is genuine but bound to a different machine — the panel will stay locked. Send the vendor the fingerprint from `license:show`.');
        }

        $this->newLine();
        $this->line('Restart the app so every worker picks it up: <options=bold>docker compose restart laravel.test</>');

        return self::SUCCESS;
    }
}
