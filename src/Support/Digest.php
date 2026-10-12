<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

use LogicException;
use Pharaonic\Fingerprint\Exceptions\ConfigurationException;

/**
 * Hashing, HMAC, output encoding and constant-time verification
 *
 * Immutable. The secret never leaves this object: it is not shown by var_dump() or print_r(),
 * and the object cannot be serialized.
 *
 * @internal
 */
final class Digest
{
    public const ENCODINGS = ['hex', 'binary', 'base64', 'base64url'];

    private string $algorithm = 'sha256';

    private ?string $secret = null;

    private string $encoding = 'hex';

    public function algorithm(): string
    {
        return $this->algorithm;
    }

    public function encoding(): string
    {
        return $this->encoding;
    }

    public function isHmac(): bool
    {
        return $this->secret !== null;
    }

    public function withAlgorithm(string $algorithm): self
    {
        if (! in_array($algorithm, hash_algos(), true)) {
            throw new ConfigurationException(sprintf('The hash algorithm "%s" is not available on PHP %s.', $algorithm, PHP_VERSION));
        }

        if ($this->secret !== null) {
            self::assertHmacAlgorithm($algorithm);
        }

        $digest = clone $this;
        $digest->algorithm = $algorithm;

        return $digest;
    }

    public function withSecret(string $secret): self
    {
        if ($secret === '') {
            throw new ConfigurationException('An HMAC secret cannot be empty.');
        }

        self::assertHmacAlgorithm($this->algorithm);

        $digest = clone $this;
        $digest->secret = $secret;

        return $digest;
    }

    public function withEncoding(string $encoding): self
    {
        if (! in_array($encoding, self::ENCODINGS, true)) {
            throw new ConfigurationException(sprintf('Unknown encoding "%s".', $encoding));
        }

        $digest = clone $this;
        $digest->encoding = $encoding;

        return $digest;
    }

    /**
     * The encoded hash, or HMAC when a secret is set, of the data
     */
    public function hash(string $data): string
    {
        $raw = $this->secret === null
            ? hash($this->algorithm, $data, true)
            : hash_hmac($this->algorithm, $data, $this->secret, true);

        return $this->encode($raw);
    }

    /**
     * Compare the data's hash with an expected one in constant time
     *
     * Hex is compared case-insensitively; the other encodings exactly.
     */
    public function verify(string $data, string $expected): bool
    {
        if ($this->encoding === 'hex') {
            $expected = strtr($expected, 'ABCDEF', 'abcdef');
        }

        return hash_equals($this->hash($data), $expected);
    }

    public function encode(string $raw): string
    {
        switch ($this->encoding) {
            case 'binary':
                return $raw;
            case 'base64':
                return base64_encode($raw);
            case 'base64url':
                return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
            default:
                return bin2hex($raw);
        }
    }

    /**
     * @return array{algorithm: string, encoding: string, hmac: bool}
     */
    public function describe(): array
    {
        return ['algorithm' => $this->algorithm, 'encoding' => $this->encoding, 'hmac' => $this->isHmac()];
    }

    /**
     * @return array{algorithm: string, encoding: string, hmac: bool}
     */
    public function __debugInfo(): array
    {
        return $this->describe();
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new LogicException('A fingerprint configuration cannot be serialized because it may hold an HMAC secret.');
    }

    private static function assertHmacAlgorithm(string $algorithm): void
    {
        if (! in_array($algorithm, hash_hmac_algos(), true)) {
            throw new ConfigurationException(sprintf('The hash algorithm "%s" cannot be used for HMAC.', $algorithm));
        }
    }
}
