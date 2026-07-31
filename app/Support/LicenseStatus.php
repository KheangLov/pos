<?php

namespace App\Support;

enum LicenseStatus: string
{
    /** No licence key installed at all. */
    case Missing = 'missing';

    /** Malformed, or the signature does not match the vendor public key. */
    case Invalid = 'invalid';

    /** Genuine key, but issued for a different machine. */
    case MachineMismatch = 'machine_mismatch';

    /** Past expiry and past the grace window. */
    case Expired = 'expired';

    /** Past expiry but still inside the grace window — keep trading. */
    case Grace = 'grace';

    /** Valid, but expiring within the warning window. */
    case Expiring = 'expiring';

    /** Valid. */
    case Valid = 'valid';

    /**
     * Whether the system should stay usable. Grace and Expiring both allow
     * access deliberately: a licence lapsing must never take a live shop
     * offline without warning, it only nags.
     */
    public function allowsAccess(): bool
    {
        return match ($this) {
            self::Valid, self::Expiring, self::Grace => true,
            self::Missing, self::Invalid, self::MachineMismatch, self::Expired => false,
        };
    }

    /** Whether the operator should be shown a banner about this state. */
    public function needsAttention(): bool
    {
        return $this !== self::Valid;
    }

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Licensed',
            self::Expiring => 'Licence expiring soon',
            self::Grace => 'Licence expired — grace period',
            self::Expired => 'Licence expired',
            self::MachineMismatch => 'Licence issued for another machine',
            self::Invalid => 'Licence key not valid',
            self::Missing => 'No licence installed',
        };
    }
}
