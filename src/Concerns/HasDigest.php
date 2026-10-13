<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Concerns;

use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Support\Digest;

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
    public function algorithm(string $algorithm): self
    {
        $builder = clone $this;
        $builder->digest = $this->digest->withAlgorithm($algorithm);

        return $builder;
    }

    /**
     * Sign with an HMAC secret instead of a plain hash
     *
     * The secret never appears in var_dump(), print_r() or exception messages, and the builder
     * cannot be serialized.
     */
    public function hmac(string $secret): self
    {
        if ($secret === '') {
            throw new ConfigurationException('An HMAC secret cannot be empty.');
        }

        // Never throw from here with a real secret: on PHP 8.1 this frame's arguments would show it
        // in the exception trace. An algorithm that cannot do HMAC is reported when hashing instead.
        $builder = clone $this;
        $builder->digest = $this->digest->withSecret($secret);

        return $builder;
    }

    /**
     * Lowercase hexadecimal output (the default)
     */
    public function hex(): self
    {
        return $this->encoding('hex');
    }

    /**
     * Raw binary output
     */
    public function binary(): self
    {
        return $this->encoding('binary');
    }

    /**
     * Standard base64 output, with padding
     */
    public function base64(): self
    {
        return $this->encoding('base64');
    }

    /**
     * URL-safe base64 output ("-" and "_"), without padding
     */
    public function base64Url(): self
    {
        return $this->encoding('base64url');
    }

    private function encoding(string $encoding): self
    {
        $builder = clone $this;
        $builder->digest = $this->digest->withEncoding($encoding);

        return $builder;
    }
}
