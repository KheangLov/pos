<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A decoded, signature-verified licence key.
 *
 * Wire format is `OMNIPOS1.<base64url(payload json)>.<base64url(ed25519 sig)>`.
 * The signature covers the exact payload bytes, so any edit to the customer
 * name, expiry date or machine binding invalidates the key. Verification only
 * needs the public half of the vendor key pair, which is why it is safe to
 * ship inside the application.
 */
final readonly class LicenseKey
{
    public const PREFIX = 'OMNIPOS1';

    public function __construct(
        public string $id,
        public string $customer,
        public ?string $machine,
        public CarbonImmutable $issuedAt,
        public ?CarbonImmutable $expiresAt,
        public ?string $notes = null,
    ) {}

    /**
     * Build the signed, shippable key string. Vendor side only — needs the
     * secret key, which never leaves your build machine.
     */
    public static function issue(self $license, string $secretKeyBase64): string
    {
        $secret = base64_decode($secretKeyBase64, true);

        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new InvalidArgumentException('Invalid Ed25519 secret key.');
        }

        $payload = json_encode($license->toPayload(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = sodium_crypto_sign_detached($payload, $secret);

        return implode('.', [
            self::PREFIX,
            self::base64UrlEncode($payload),
            self::base64UrlEncode($signature),
        ]);
    }

    /**
     * Decode and verify a key string. Returns null when the key is malformed,
     * or when the signature does not match the shipped public key — the two
     * cases are deliberately indistinguishable to the caller so a tampered key
     * cannot be told apart from a typo'd one.
     */
    public static function verify(string $key, string $publicKeyBase64): ?self
    {
        $public = base64_decode($publicKeyBase64, true);

        if ($public === false || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        $parts = explode('.', trim($key));

        if (count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            return null;
        }

        $payload = self::base64UrlDecode($parts[1]);
        $signature = self::base64UrlDecode($parts[2]);

        if ($payload === null || $signature === null || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        if (! sodium_crypto_sign_verify_detached($signature, $payload, $public)) {
            return null;
        }

        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($data) || ! isset($data['id'], $data['customer'], $data['issued_at'])) {
            return null;
        }

        return new self(
            id: (string) $data['id'],
            customer: (string) $data['customer'],
            machine: isset($data['machine']) && $data['machine'] !== null ? (string) $data['machine'] : null,
            issuedAt: CarbonImmutable::parse($data['issued_at']),
            expiresAt: isset($data['expires_at']) && $data['expires_at'] !== null
                ? CarbonImmutable::parse($data['expires_at'])->endOfDay()
                : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        );
    }

    public function isPerpetual(): bool
    {
        return $this->expiresAt === null;
    }

    public function isExpired(?CarbonImmutable $now = null): bool
    {
        return ! $this->isPerpetual()
            && ($now ?? CarbonImmutable::now())->greaterThan($this->expiresAt);
    }

    /**
     * Whole days until expiry; negative once expired, null when perpetual.
     */
    public function daysUntilExpiry(?CarbonImmutable $now = null): ?int
    {
        if ($this->isPerpetual()) {
            return null;
        }

        return (int) ($now ?? CarbonImmutable::now())->startOfDay()
            ->diffInDays($this->expiresAt->startOfDay(), false);
    }

    public function matchesMachine(?string $fingerprint): bool
    {
        // A licence issued without a binding is portable by design.
        if ($this->machine === null) {
            return true;
        }

        return $fingerprint !== null && hash_equals($this->machine, $fingerprint);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'customer' => $this->customer,
            'machine' => $this->machine,
            'issued_at' => $this->issuedAt->toDateString(),
            'expires_at' => $this->expiresAt?->toDateString(),
            'notes' => $this->notes,
        ];
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
