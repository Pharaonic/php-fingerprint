<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a value cannot be turned into a deterministic canonical form.
 */
class UnsupportedValueException extends InvalidArgumentException implements FingerprintException
{
    public static function at(string $path, string $reason): self
    {
        return new self(sprintf('%s %s.', $reason, self::where($path)));
    }

    protected static function where(string $path): string
    {
        return $path === '' ? 'at the root' : sprintf('at path "%s"', $path);
    }
}
