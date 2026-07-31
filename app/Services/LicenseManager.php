<?php

namespace App\Services;

use App\Support\LicenseKey;
use App\Support\LicenseStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Resolves the installed licence into a status the app can act on.
 *
 * Bound as a scoped singleton so Octane's long-lived workers re-resolve it
 * every request — otherwise a licence installed at runtime would not be seen
 * until the workers restarted.
 */
class LicenseManager
{
    private ?LicenseKey $license = null;

    private ?LicenseStatus $status = null;

    private bool $resolved = false;

    public function status(): LicenseStatus
    {
        $this->resolve();

        return $this->status;
    }

    public function license(): ?LicenseKey
    {
        $this->resolve();

        return $this->license;
    }

    public function allowsAccess(): bool
    {
        if (! config('license.enforce')) {
            return true;
        }

        return $this->status()->allowsAccess();
    }

    /**
     * Human-readable explanation of the current state, for banners and the
     * blocking screen.
     */
    public function message(): ?string
    {
        $status = $this->status();
        $license = $this->license();
        $grace = (int) config('license.grace_days');

        return match ($status) {
            LicenseStatus::Valid => null,
            LicenseStatus::Expiring => sprintf(
                'This licence expires on %s (%d days). Contact %s to renew.',
                $license->expiresAt->toFormattedDateString(),
                max(0, $license->daysUntilExpiry()),
                config('license.vendor'),
            ),
            LicenseStatus::Grace => sprintf(
                'This licence expired on %s. The system keeps working for %d more day(s), then the admin panel will be locked. Contact %s now.',
                $license->expiresAt->toFormattedDateString(),
                max(0, $grace + $license->daysUntilExpiry()),
                config('license.vendor'),
            ),
            LicenseStatus::Expired => sprintf(
                'This licence expired on %s and the grace period has ended.',
                $license->expiresAt->toFormattedDateString(),
            ),
            LicenseStatus::MachineMismatch => 'This licence was issued for a different machine. A licence covers the installation it was issued for.',
            LicenseStatus::Invalid => 'The installed licence key could not be verified. It may be incomplete, edited, or issued by a different vendor key.',
            LicenseStatus::Missing => 'No licence key is installed on this system.',
        };
    }

    /**
     * Persist a licence key to the storage volume. Verified before writing so
     * a bad paste fails loudly at install time rather than silently locking
     * the panel later.
     */
    public function install(string $key): LicenseKey
    {
        $key = trim($key);
        $license = LicenseKey::verify($key, (string) config('license.public_key'));

        if (! $license instanceof LicenseKey) {
            throw new RuntimeException('That licence key could not be verified against this build.');
        }

        $path = (string) config('license.key_path');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create {$directory}.");
        }

        file_put_contents($path, $key.PHP_EOL);

        $this->flush();

        return $license;
    }

    /**
     * Fingerprint of the host this installation is running on.
     *
     * Prefers LICENSE_MACHINE_ID, which start-prod.bat derives from real
     * Windows host hardware. Falls back to a random identifier persisted on
     * the storage volume — weaker (it travels with a copied volume) but still
     * pins the licence to one installation.
     */
    public function fingerprint(): string
    {
        $machineId = config('license.machine_id');

        if (filled($machineId)) {
            return hash('sha256', 'omnipos:'.$machineId);
        }

        return hash('sha256', 'omnipos-install:'.$this->installId());
    }

    /** Whether the fingerprint comes from real host hardware. */
    public function hasHostBinding(): bool
    {
        return filled(config('license.machine_id'));
    }

    public function flush(): void
    {
        $this->resolved = false;
        $this->license = null;
        $this->status = null;
    }

    private function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        $this->resolved = true;

        $key = $this->readKey();

        if ($key === null) {
            $this->status = LicenseStatus::Missing;

            return;
        }

        $license = LicenseKey::verify($key, (string) config('license.public_key'));

        if (! $license instanceof LicenseKey) {
            $this->status = LicenseStatus::Invalid;

            return;
        }

        $this->license = $license;

        if (! $license->matchesMachine($this->fingerprint())) {
            $this->status = LicenseStatus::MachineMismatch;

            return;
        }

        $this->status = $this->expiryStatus($license);
    }

    private function expiryStatus(LicenseKey $license): LicenseStatus
    {
        if ($license->isPerpetual()) {
            return LicenseStatus::Valid;
        }

        $daysLeft = $license->daysUntilExpiry();

        if ($daysLeft >= 0) {
            return $daysLeft <= (int) config('license.warn_days')
                ? LicenseStatus::Expiring
                : LicenseStatus::Valid;
        }

        return abs($daysLeft) <= (int) config('license.grace_days')
            ? LicenseStatus::Grace
            : LicenseStatus::Expired;
    }

    private function readKey(): ?string
    {
        $configured = config('license.key');

        if (filled($configured)) {
            return trim((string) $configured);
        }

        $path = (string) config('license.key_path');

        if (! is_file($path)) {
            return null;
        }

        $contents = trim((string) file_get_contents($path));

        return $contents === '' ? null : $contents;
    }

    private function installId(): string
    {
        $path = (string) config('license.install_id_path');

        if (is_file($path)) {
            $existing = trim((string) file_get_contents($path));

            if ($existing !== '') {
                return $existing;
            }
        }

        $id = (string) Str::uuid();
        $directory = dirname($path);

        if (is_dir($directory) || mkdir($directory, 0755, true) || is_dir($directory)) {
            file_put_contents($path, $id.PHP_EOL);
        }

        return $id;
    }

    /** Exposed for the `license:show` command. */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }
}
