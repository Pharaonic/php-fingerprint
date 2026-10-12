<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

use LogicException;
use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Exceptions\UnreadableResourceException;

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

    /**
     * Never throws, so the secret cannot end up in an exception trace (PHP 8.0 has no
     * #[SensitiveParameter]); hash() and the file and stream methods check the algorithm instead
     */
    public function withSecret(string $secret): self
    {
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
        $this->assertUsable();

        $raw = $this->secret === null
            ? hash($this->algorithm, $data, true)
            : hash_hmac($this->algorithm, $data, $this->secret, true);

        return $this->encode($raw);
    }

    /**
     * The encoded hash, or HMAC, of a file's bytes, read in chunks
     */
    public function hashFile(string $path): string
    {
        $this->assertUsable();

        if (! is_file($path) || ! is_readable($path)) {
            throw new UnreadableResourceException(sprintf('The file "%s" does not exist or cannot be read.', $path));
        }

        $raw = $this->secret === null
            ? @hash_file($this->algorithm, $path, true)
            : @hash_hmac_file($this->algorithm, $path, $this->secret, true);

        if ($raw === false) {
            throw new UnreadableResourceException(sprintf('The file "%s" could not be read.', $path));
        }

        return $this->encode($raw);
    }

    /**
     * The encoded hash, or HMAC, of a stream's bytes from its current position to its end
     *
     * @param resource $stream
     */
    public function hashStream($stream): string
    {
        self::assertReadableStream($stream);
        $this->assertUsable();

        $context = $this->secret === null
            ? hash_init($this->algorithm)
            : hash_init($this->algorithm, HASH_HMAC, $this->secret);

        hash_update_stream($context, $stream);

        return $this->encode(hash_final($context, true));
    }

    /**
     * Compare the data's hash with an expected one in constant time
     *
     * Hex is compared case-insensitively; the other encodings exactly.
     */
    public function verify(string $data, string $expected): bool
    {
        return $this->matches($this->hash($data), $expected);
    }

    /**
     * Compare an encoded hash with an expected one in constant time
     */
    public function matches(string $hash, string $expected): bool
    {
        if ($this->encoding === 'hex') {
            $expected = strtr($expected, 'ABCDEF', 'abcdef');
        }

        return hash_equals($hash, $expected);
    }

    public static function assertReadableStream(mixed $stream): void
    {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new UnreadableResourceException(sprintf('Expected an open stream, %s given.', get_debug_type($stream)));
        }

        $mode = stream_get_meta_data($stream)['mode'];

        if (strpbrk($mode, 'r+') === false) {
            throw new UnreadableResourceException(sprintf('The stream is not readable (mode "%s").', $mode));
        }
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

    private function assertUsable(): void
    {
        if ($this->secret !== null) {
            self::assertHmacAlgorithm($this->algorithm);
        }
    }

    private static function assertHmacAlgorithm(string $algorithm): void
    {
        if (! in_array($algorithm, hash_hmac_algos(), true)) {
            throw new ConfigurationException(sprintf('The hash algorithm "%s" cannot be used for HMAC.', $algorithm));
        }
    }
}
