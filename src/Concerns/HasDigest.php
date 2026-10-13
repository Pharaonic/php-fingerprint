<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Concerns;

use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Support\Digest;
use SensitiveParameter;
use NoDiscard;

/**
 * Algorithm, HMAC and output encoding options shared by every builder
 *
 * @internal
 */
trait HasDigest
{
    private Digest $digest;

    /**
     * Hash with another algorithm available to hash() (sha256 by default)
     *
     * Non-cryptographic algorithms such as crc32b, fnv1a64 or md5 are fine for change detection
     * but must not protect anything.
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function algorithm(string $algorithm): self
    {
        return clone($this, ['digest' => $this->digest->withAlgorithm($algorithm)]);
    }

    /**
     * Sign with an HMAC secret instead of a plain hash
     *
     * The secret never appears in var_dump(), print_r(), exception messages or exception traces,
     * and the builder cannot be serialized.
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function hmac(#[SensitiveParameter] string $secret): self
    {
        if ($secret === '') {
            throw new ConfigurationException('An HMAC secret cannot be empty.');
        }

        // An algorithm that cannot do HMAC is reported when hashing, not here.
        return clone($this, ['digest' => $this->digest->withSecret($secret)]);
    }

    /**
     * Lowercase hexadecimal output (the default)
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function hex(): self
    {
        return $this->encoding('hex');
    }

    /**
     * Raw binary output
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function binary(): self
    {
        return $this->encoding('binary');
    }

    /**
     * Standard base64 output, with padding
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function base64(): self
    {
        return $this->encoding('base64');
    }

    /**
     * URL-safe base64 output ("-" and "_"), without padding
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function base64Url(): self
    {
        return $this->encoding('base64url');
    }

    private function encoding(string $encoding): self
    {
        return clone($this, ['digest' => $this->digest->withEncoding($encoding)]);
    }
}
