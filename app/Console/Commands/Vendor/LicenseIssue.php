<?php

namespace App\Console\Commands\Vendor;

use App\Support\LicenseKey;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * VENDOR ONLY — stripped from customer builds by Dockerfile.prod.
 *
 * Issues a signed licence key for one customer. The machine fingerprint comes
 * from the customer running `php artisan license:show` on their install and
 * sending you the value it prints.
 */
class LicenseIssue extends Command
{
    protected $signature = 'license:issue
        {--customer= : Customer name printed on the licence}
        {--machine= : Machine fingerprint from the customer\'s license:show (omit for a portable licence)}
        {--months=12 : Licence term in months}
        {--expires= : Exact expiry date (YYYY-MM-DD), overrides --months}
        {--perpetual : Issue a licence that never expires}
        {--notes= : Free-text note stored in the licence}
        {--secret= : Base64 Ed25519 secret key (falls back to the LICENSE_SECRET_KEY env var)}';

    protected $description = '[vendor] Issue a signed licence key for a customer';

    public function handle(): int
    {
        $secret = (string) ($this->option('secret') ?: env('LICENSE_SECRET_KEY', ''));

        if ($secret === '') {
            $secret = (string) $this->secret('Ed25519 secret key (base64)');
        }

        if (trim($secret) === '') {
            $this->components->error('A signing secret key is required. Generate one with `license:keygen`.');

            return self::FAILURE;
        }

        $customer = (string) ($this->option('customer') ?: $this->ask('Customer name'));

        if (trim($customer) === '') {
            $this->components->error('A customer name is required.');

            return self::FAILURE;
        }

        $machine = $this->option('machine');
        $machine = filled($machine) ? trim((string) $machine) : null;

        $issuedAt = CarbonImmutable::now();

        if ($this->option('perpetual')) {
            $expiresAt = null;
        } elseif (filled($this->option('expires'))) {
            try {
                $expiresAt = CarbonImmutable::parse((string) $this->option('expires'));
            } catch (\Throwable) {
                $this->components->error('--expires must be a date like 2027-06-30.');

                return self::FAILURE;
            }
        } else {
            $expiresAt = $issuedAt->addMonths(max(1, (int) $this->option('months')));
        }

        $license = new LicenseKey(
            id: (string) Str::uuid(),
            customer: trim($customer),
            machine: $machine,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            notes: $this->option('notes') ? (string) $this->option('notes') : null,
        );

        try {
            $key = LicenseKey::issue($license, trim($secret));
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail('Licence ID', $license->id);
        $this->components->twoColumnDetail('Customer', $license->customer);
        $this->components->twoColumnDetail('Machine binding', $machine ?? '<fg=yellow>none (portable)</>');
        $this->components->twoColumnDetail('Issued', $license->issuedAt->toDateString());
        $this->components->twoColumnDetail('Expires', $expiresAt?->toDateString() ?? 'never');
        $this->newLine();

        $this->line('<options=bold>Licence key — send this to the customer:</>');
        $this->newLine();
        $this->line($key);
        $this->newLine();
        $this->components->info('They install it with: php artisan license:install "<key>"');

        return self::SUCCESS;
    }
}
