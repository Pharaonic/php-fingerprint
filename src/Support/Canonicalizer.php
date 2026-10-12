<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

use Closure;
use Pharaonic\Fingerprint\Exceptions\MaximumDepthExceededException;
use Pharaonic\Fingerprint\Exceptions\UnsupportedValueException;

/**
 * Walks a PHP value once and produces its canonical document
 *
 * @internal
 */
final class Canonicalizer
{
    public const DEFAULT_MAX_DEPTH = 512;

    public function __construct(
        private ?string $namespace = null,
        private int $maxDepth = self::DEFAULT_MAX_DEPTH,
    ) {
    }

    public function canonicalize(mixed $value): string
    {
        return Encoder::document($this->namespace, $this->encode($value, [], 0));
    }

    /**
     * @param list<int|string> $path
     */
    private function encode(mixed $value, array $path, int $depth): string
    {
        if ($value === null) {
            return Encoder::null();
        }

        if (is_bool($value)) {
            return Encoder::bool($value);
        }

        if (is_int($value)) {
            return Encoder::int($value);
        }

        if (is_float($value)) {
            if (is_nan($value)) {
                throw UnsupportedValueException::at(Path::format($path), 'NAN cannot be fingerprinted because it is not equal to itself');
            }

            return Encoder::float($value);
        }

        if (is_string($value)) {
            return Encoder::string($value);
        }

        if (is_array($value)) {
            return $this->encodeArray($value, $path, $depth + 1);
        }

        throw UnsupportedValueException::at(Path::format($path), self::describe($value) . ' cannot be fingerprinted');
    }

    /**
     * @param array<mixed> $value
     * @param list<int|string> $path
     */
    private function encodeArray(array $value, array $path, int $depth): string
    {
        if ($depth > $this->maxDepth) {
            throw MaximumDepthExceededException::limit(Path::format($path), $this->maxDepth);
        }

        if (self::isList($value)) {
            $items = [];

            foreach ($value as $index => $item) {
                $items[] = $this->encode($item, [...$path, $index], $depth);
            }

            return Encoder::list($items);
        }

        $entries = [];

        foreach ($value as $key => $item) {
            $entries[Encoder::key($key)] = $this->encode($item, [...$path, $key], $depth);
        }

        return Encoder::map($entries);
    }

    /**
     * Keys are exactly 0..n-1 in order (array_is_list() only exists from PHP 8.1)
     *
     * @param array<mixed> $value
     */
    public static function isList(array $value): bool
    {
        $expected = 0;

        foreach ($value as $key => $_) {
            if ($key !== $expected++) {
                return false;
            }
        }

        return true;
    }

    private static function describe(mixed $value): string
    {
        if ($value instanceof Closure) {
            return 'A closure';
        }

        if (is_object($value)) {
            return sprintf('An object of class %s', get_class($value));
        }

        if (is_resource($value)) {
            return sprintf('A %s resource', get_resource_type($value));
        }

        // Closed resources report "resource (closed)" from get_debug_type().
        return sprintf('A value of type %s', get_debug_type($value));
    }
}
