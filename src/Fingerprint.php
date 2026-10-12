<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint;

/**
 * Deterministic fingerprints of PHP values
 *
 * Associative arrays are compared regardless of key order, lists keep their order,
 * and types are preserved: 1, '1', 1.0 and true all give different fingerprints.
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
}
