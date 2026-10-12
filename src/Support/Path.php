<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

use Pharaonic\Fingerprint\Exceptions\ConfigurationException;

/**
 * Dot-notation paths, with the same syntax as pharaonic/php-dot-array
 *
 * Segments are joined with ".", a segment that is exactly "*" matches any one key or list index,
 * and a literal ".", "*" or "\" inside a key is escaped with "\". Unlike php-dot-array, paths are
 * never trimmed: an empty path or an empty segment is rejected.
 *
 * A parsed pattern is a list of segments where null stands for the "*" wildcard.
 *
 * @internal
 */
final class Path
{
    /**
     * @return non-empty-list<string|null>
     */
    public static function parse(string $path): array
    {
        if ($path === '') {
            throw new ConfigurationException('A path cannot be empty.');
        }

        $segments = [];
        $segment = '';
        $escaped = false;
        $length = strlen($path);

        for ($i = 0; $i < $length; $i++) {
            $char = $path[$i];

            if ($char === '\\' && $i + 1 < $length && strpos('.*\\', $path[$i + 1]) !== false) {
                $segment .= $path[++$i];
                $escaped = true;
            } elseif ($char === '.') {
                $segments[] = self::segment($path, $segment, $escaped);
                $segment = '';
                $escaped = false;
            } else {
                $segment .= $char;
            }
        }

        $segments[] = self::segment($path, $segment, $escaped);

        return $segments;
    }

    private static function segment(string $path, string $segment, bool $escaped): ?string
    {
        if ($segment === '') {
            throw new ConfigurationException(sprintf('The path "%s" has an empty segment.', $path));
        }

        return $segment === '*' && ! $escaped ? null : $segment;
    }

    /**
     * @param list<string|null> $pattern
     * @param list<int|string> $path
     */
    public static function matches(array $pattern, array $path): bool
    {
        return count($pattern) === count($path) && self::startsWith($pattern, $path);
    }

    /**
     * Whether the path is a strict ancestor of what the pattern matches
     *
     * @param list<string|null> $pattern
     * @param list<int|string> $path
     */
    public static function leadsTo(array $pattern, array $path): bool
    {
        return count($pattern) > count($path) && self::startsWith($pattern, $path);
    }

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

    /**
     * @param list<string|null> $pattern
     * @param list<int|string> $path
     */
    private static function startsWith(array $pattern, array $path): bool
    {
        foreach ($path as $i => $key) {
            if ($pattern[$i] !== null && $pattern[$i] !== (string) $key) {
                return false;
            }
        }

        return true;
    }
}
