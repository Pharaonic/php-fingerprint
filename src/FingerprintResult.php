<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint;

/**
 * A fingerprint with everything needed to understand it, for debugging and logging
 *
 * Compare canonical() of two results to see why their fingerprints differ. A result never
 * holds the HMAC secret.
 */
final readonly class FingerprintResult
{
    /**
     * @internal Use FingerprintBuilder::result().
     */
    public function __construct(
        private string $hash,
        private string $algorithm,
        private string $encoding,
        private bool $hmac,
        private ?string $namespace,
        private string $version,
        private string $canonical,
        private mixed $normalized,
    ) {}

    /**
     * The fingerprint, as make() returns it
     */
    public function hash(): string
    {
        return $this->hash;
    }

    public function algorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * hex, binary, base64 or base64url
     */
    public function encoding(): string
    {
        return $this->encoding;
    }

    /**
     * Whether the fingerprint is an HMAC
     */
    public function hmac(): bool
    {
        return $this->hmac;
    }

    public function namespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * The canonical format version, such as "v1"
     */
    public function version(): string
    {
        return $this->version;
    }

    /**
     * The exact bytes that were hashed
     */
    public function canonical(): string
    {
        return $this->canonical;
    }

    /**
     * The value after filters and value rules, as plain PHP data
     *
     * Dates appear as ISO 8601 strings: "2026-10-12T08:00:00.000000Z", or the local time
     * followed by the timezone name when keepTimezone() is used.
     */
    public function normalized(): mixed
    {
        return $this->normalized;
    }

    /**
     * @return array{hash: string, algorithm: string, encoding: string, hmac: bool, namespace: string|null, version: string, canonical: string, normalized: mixed}
     */
    public function toArray(): array
    {
        return [
            'hash' => $this->hash,
            'algorithm' => $this->algorithm,
            'encoding' => $this->encoding,
            'hmac' => $this->hmac,
            'namespace' => $this->namespace,
            'version' => $this->version,
            'canonical' => $this->canonical,
            'normalized' => $this->normalized,
        ];
    }
}
