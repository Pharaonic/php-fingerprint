<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Exceptions;

/**
 * Thrown when a value nests deeper than the allowed depth, which includes self-referencing arrays.
 */
final class MaximumDepthExceededException extends UnsupportedValueException
{
    public static function limit(string $path, int $maxDepth): self
    {
        return new self(sprintf(
            'The value is nested deeper than the maximum depth of %d %s; it may reference itself.',
            $maxDepth,
            self::where($path),
        ));
    }
}
