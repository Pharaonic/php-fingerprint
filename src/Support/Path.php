<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

/**
 * Dot-notation paths, with the same syntax as pharaonic/php-dot-array
 *
 * Segments are joined with ".", and a literal ".", "*" or "\" inside a key is escaped with "\".
 *
 * @internal
 */
final class Path
{
    /**
     * @param list<int|string> $segments
     */
    public static function format(array $segments): string
    {
        return implode('.', array_map(
            static fn (int|string $segment): string => addcslashes((string) $segment, '.*\\'),
            $segments,
        ));
    }
}
