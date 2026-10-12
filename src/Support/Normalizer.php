<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

use Closure;
use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Exceptions\MaximumDepthExceededException;
use Pharaonic\Fingerprint\Exceptions\UnsupportedValueException;
use stdClass;

/**
 * Turns a PHP value into a normalized tree, applying filters and value rules in one walk
 *
 * The tree holds null, booleans, integers, floats, strings, lists (plain arrays) and maps (Map).
 *
 * For every child, in this order: only(), ignore(), ignoreWhen(), transform(), its own children,
 * list rules (ordering, then de-duplication), then ignoreNulls() / ignoreEmptyStrings() /
 * ignoreEmptyArrays() on the final value.
 * Lists stay lists and are re-indexed after removals; paths always use the input keys.
 *
 * @internal
 */
final class Normalizer
{
    private stdClass $drop;

    public function __construct(
        private Rules $rules,
        private int $maxDepth,
    ) {
        $this->drop = new stdClass();
    }

    public function normalize(mixed $value): mixed
    {
        return $this->node($value, [], 0, $this->rules->only === []);
    }

    /**
     * The normalized value, or $this->drop when a filter removes it
     *
     * @param list<int|string> $path
     * @param bool $inside Whether only() already selected this node, or does not apply.
     */
    private function node(mixed $value, array $path, int $depth, bool $inside): mixed
    {
        if ($path !== []) {
            foreach ($this->rules->ignoreWhen as $callback) {
                $ignore = $callback(Path::format($path), $value);

                if (! is_bool($ignore)) {
                    throw new ConfigurationException(sprintf(
                        'An ignoreWhen() callback must return a boolean, %s returned at path "%s".',
                        get_debug_type($ignore),
                        Path::format($path),
                    ));
                }

                if ($ignore) {
                    return $this->drop;
                }
            }
        }

        $rules = $this->rules->valuesFor($path);
        $transform = $rules['transform'] ?? null;

        if (is_callable($transform)) {
            $value = $transform($value, Path::format($path));
        }

        if (is_array($value)) {
            $value = $this->container($value, $path, $depth + 1, $inside);

            if (is_array($value)) {
                $order = $rules['order'] ?? null;
                $unique = $rules['unique'] ?? null;

                // Ordering first, so de-duplication keeps the first occurrence in the final order.
                if ($order instanceof ListOrder) {
                    $value = $order->apply($value, Path::format($path));
                }

                if ($unique instanceof ListUnique) {
                    $value = $unique->apply($value);
                }
            }
        } else {
            $this->assertScalar($value, $path);
        }

        if ($path === []) {
            return $value;
        }

        if (! $inside && ! self::hasItems($value)) {
            return $this->drop; // An ancestor kept by only() that ended up without a selected child.
        }

        if (
            ($value === null && $this->rules->ignoreNulls)
            || ($value === '' && $this->rules->ignoreEmptyStrings)
            || ($this->rules->ignoreEmptyArrays && (is_array($value) || $value instanceof Map) && ! self::hasItems($value))
        ) {
            return $this->drop;
        }

        return $value;
    }

    /**
     * @param array<mixed> $value
     * @param list<int|string> $path
     * @return list<mixed>|Map
     */
    private function container(array $value, array $path, int $depth, bool $inside): array|Map
    {
        if ($depth > $this->maxDepth) {
            throw MaximumDepthExceededException::limit(Path::format($path), $this->maxDepth);
        }

        $items = [];

        foreach ($value as $key => $child) {
            $childPath = [...$path, $key];
            $childInside = $inside || Rules::anyMatches($this->rules->only, $childPath);

            if (! $childInside && ! Rules::anyLeadsTo($this->rules->only, $childPath)) {
                continue;
            }

            if (Rules::anyMatches($this->rules->ignore, $childPath)) {
                continue;
            }

            $child = $this->node($child, $childPath, $depth, $childInside);

            if ($child !== $this->drop) {
                $items[$key] = $child;
            }
        }

        return Canonicalizer::isList($value) ? array_values($items) : new Map($items);
    }

    /**
     * @param list<int|string> $path
     */
    private function assertScalar(mixed $value, array $path): void
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return;
        }

        if (is_float($value)) {
            if (is_nan($value)) {
                throw UnsupportedValueException::at(Path::format($path), 'NAN cannot be fingerprinted because it is not equal to itself');
            }

            return;
        }

        throw UnsupportedValueException::at(Path::format($path), self::describe($value) . ' cannot be fingerprinted');
    }

    private static function hasItems(mixed $value): bool
    {
        return (is_array($value) && $value !== []) || ($value instanceof Map && $value->items !== []);
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
