<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint;

use LogicException;
use Pharaonic\Fingerprint\Concerns\HasDigest;
use Pharaonic\Fingerprint\Support\Digest;
use NoDiscard;

/**
 * Immutable fingerprint of raw bytes from a file or a stream
 *
 * The bytes are hashed exactly as they are, in chunks, so large files never load into memory.
 * A stream is read from its current position to its end; it is never rewound or closed, so a
 * second make() on the same stream only sees what is left.
 */
final class DigestBuilder
{
    use HasDigest;

    /** @var string|resource */
    private $source;

    /**
     * @internal Use Fingerprint::file() or Fingerprint::stream().
     *
     * @param string|resource $source A file path or a readable stream.
     */
    public function __construct($source)
    {
        $this->source = $source;
        $this->digest = new Digest();
    }

    #[NoDiscard]
    public function make(): string
    {
        return is_string($this->source)
            ? $this->digest->hashFile($this->source)
            : $this->digest->hashStream($this->source);
    }

    /**
     * Whether the bytes match an expected fingerprint, compared in constant time
     *
     * Hex fingerprints are compared case-insensitively, other encodings exactly.
     */
    #[NoDiscard]
    public function verify(string $expected): bool
    {
        return $this->digest->matches($this->make(), $expected);
    }

    /**
     * @return array{algorithm: string, encoding: string, hmac: bool}
     */
    public function __debugInfo(): array
    {
        return $this->digest->describe();
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new LogicException('A digest builder cannot be serialized because it may hold an HMAC secret.');
    }
}
