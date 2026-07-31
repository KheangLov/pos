<?php

namespace App\Console\Commands;

use App\Services\LicenseManager;
use App\Support\LicenseStatus;
use Illuminate\Console\Command;

class LicenseShow extends Command
{
    protected $signature = 'license:show {--fingerprint : Print only the machine fingerprint}';

    protected $description = 'Show licence status and this machine\'s fingerprint';

    public function handle(LicenseManager $licenses): int
    {
        if ($this->option('fingerprint')) {
            $this->line($licenses->fingerprint());

            return self::SUCCESS;
        }

        $status = $licenses->status();
        $license = $licenses->license();

        $this->newLine();
        $this->components->twoColumnDetail(
            'Status',
            match (true) {
                $status === LicenseStatus::Valid => '<fg=green;options=bold>'.$status->label().'</>',
                $status->allowsAccess() => '<fg=yellow;options=bold>'.$status->label().'</>',
                default => '<fg=red;options=bold>'.$status->label().'</>',
            },
        );

        if ($license !== null) {
            $this->components->twoColumnDetail('Licence ID', $license->id);
            $this->components->twoColumnDetail('Customer', $license->customer);
            $this->components->twoColumnDetail('Issued', $license->issuedAt->toFormattedDateString());
            $this->components->twoColumnDetail('Expires', $license->expiresAt?->toFormattedDateString() ?? 'never');

            if ($license->notes !== null) {
                $this->components->twoColumnDetail('Notes', $license->notes);
            }
        }

        $this->components->twoColumnDetail('Enforcement', config('license.enforce') ? 'on' : '<fg=yellow>off</>');
        $this->components->twoColumnDetail(
            'Machine fingerprint',
            $licenses->fingerprint().($licenses->hasHostBinding() ? '' : ' <fg=yellow>(install-scoped — no host binding)</>'),
        );

        if ($message = $licenses->message()) {
            $this->newLine();
            $status->allowsAccess()
                ? $this->components->warn($message)
                : $this->components->error($message);
        }

        $this->newLine();
        $this->line('Send the fingerprint above to '.config('license.vendor').' to have a licence issued for this machine.');

        return self::SUCCESS;
    }
}
