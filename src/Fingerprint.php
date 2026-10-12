<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint;

use Pharaonic\Fingerprint\Support\Digest;

/**
 * Deterministic fingerprints of PHP values
 *
 * Associative arrays are compared regardless of key order, lists keep their order,
 * and types are preserved: 1, '1', 1.0 and true all give different fingerprints.
 *
 * Fingerprints answer "are these the same data?"; signatures answer "does this payload carry
 * this protocol-defined, ordered signature?". The two never share a format.
 */
final class Fingerprint
{
    /**
     * The SHA-256 fingerprint of a value, as lowercase hex
     */
    public static function make(mixed $value): string
    {
        return self::for($value)->make();
    }

    /**
     * Whether a value matches an expected SHA-256 hex fingerprint, compared in constant time
     */
    public static function verify(mixed $value, string $expected): bool
    {
        return self::for($value)->verify($expected);
    }

    /**
     * Start configuring the fingerprint of a value
     */
    public static function for(mixed $value): FingerprintBuilder
    {
        return new FingerprintBuilder($value);
    }

    /**
     * Start a fingerprint of a file's raw bytes, read in chunks
     */
    public static function file(string $path): DigestBuilder
    {
        return new DigestBuilder($path);
    }

    /**
     * Start a fingerprint of a stream's raw bytes, from its current position to its end
     *
     * The stream is never rewound or closed.
     *
     * @param resource $stream
     */
    public static function stream($stream): DigestBuilder
    {
        Digest::assertReadableStream($stream);

        return new DigestBuilder($stream);
    }

    /**
     * Start an ordered signature of payload fields, for protocols where field order matters
     *
     * Signatures never sort anything: declare the fields with fields(), in the protocol's order.
     *
     * @param array<mixed>|object $payload
     */
    public static function signature(array|object $payload): SignatureBuilder
    {
        return new SignatureBuilder($payload);
    }
}
