<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Exceptions;

use UnexpectedValueException;

/**
 * Thrown when a signature field is missing from the payload and onMissing() is "throw" (the default).
 */
final class MissingFieldException extends UnexpectedValueException implements FingerprintException
{
    public static function field(string $field): self
    {
        return new self(sprintf('The field "%s" is missing from the payload.', $field));
    }
}
