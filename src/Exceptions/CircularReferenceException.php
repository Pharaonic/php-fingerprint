<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Exceptions;

/**
 * Thrown when an object contains itself, directly or through other objects or normalizers.
 */
final class CircularReferenceException extends UnsupportedValueException
{
    public static function of(string $path, string $class): self
    {
        return new self(sprintf('An object of class %s contains itself %s.', $class, self::where($path)));
    }
}
