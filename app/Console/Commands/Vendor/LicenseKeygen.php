<?php

namespace App\Console\Commands\Vendor;

use Illuminate\Console\Command;

/**
 * VENDOR ONLY — stripped from customer builds by Dockerfile.prod.
 *
 * Generates the Ed25519 key pair that signs every licence. Run this once,
 * keep the secret key in your own password manager, and never put it on a
 * machine you deliver to a customer. Losing it means you can no longer issue
 * licences that existing installs accept; leaking it means anyone can.
 */
class LicenseKeygen extends Command
{
    protected $signature = 'license:keygen';

    protected $description = '[vendor] Generate an Ed25519 licence signing key pair';

    public function handle(): int
    {
        $pair = sodium_crypto_sign_keypair();

        $secret = base64_encode(sodium_crypto_sign_secretkey($pair));
        $public = base64_encode(sodium_crypto_sign_publickey($pair));

        $this->newLine();
        $this->components->info('Licence key pair generated.');
        $this->newLine();

        $this->line('<fg=yellow;options=bold>SECRET KEY — keep this off every delivered machine:</>');
        $this->line($secret);
        $this->newLine();

        $this->line('<fg=green;options=bold>PUBLIC KEY — set LICENSE_PUBLIC_KEY to this in the release .env:</>');
        $this->line($public);
        $this->newLine();

        $this->components->warn('Store the secret key now. It is not written to disk and cannot be recovered.');

        return self::SUCCESS;
    }
}
