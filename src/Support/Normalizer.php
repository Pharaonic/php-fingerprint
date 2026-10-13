<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

use Closure;
use DateTimeInterface;
use Generator;
use JsonSerializable;
use Pharaonic\Fingerprint\Exceptions\CircularReferenceException;
use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Exceptions\MaximumDepthExceededException;
use Pharaonic\Fingerprint\Exceptions\UnsupportedValueException;
use stdClass;
use Traversable;

/**
 * Turns a PHP value into a normalized tree, applying filters and value rules in one walk
 *
 * The tree holds null, booleans, integers, floats, strings, dates (Date), lists (plain arrays)
 * and maps (Map).
 *
 * For every value, in this order: objects become plain data, ignoreWhen(), transform() (whose
 * result is converted again), string rules, number rules, boolean rules, date rules, its own
 * children, then list rules (ordering, then de-duplication). For a child, only() and ignore()
 * run first, and ignoreNulls() / ignoreEmptyStrings() / ignoreEmptyArrays() check its final value.
 * Lists stay lists and are re-indexed after removals; paths always use the input keys.
 *
 * Objects, in this order: closures are rejected; normalize() callbacks (an exact class first,
 * then the first matching class or interface in declaration order); DateTimeInterface stays a
 * date; JsonSerializable; Traversable (except generators); stdClass, always a map. Anything else
 * is rejected, and private or protected state is never read.
 *
 * @internal
 */
final class Normalizer
{
    private stdClass $drop;

    /**
     * Objects being converted along the current path, by spl_object_id()
     *
     * Holding the objects keeps them alive, so their ids cannot be reused while they are active.
     *
     * @var array<int, object>
     */
    private array $active = [];

    /** Whether no value rule or ignoreWhen() callback applies, so scalars can skip the full pipeline */
    private bool $plainScalars;

    public function __construct(
        private Rules $rules,
        private int $maxDepth,
    ) {
        $this->drop = new stdClass();
        $this->plainScalars = $rules->values === [] && $rules->ignoreWhen === [];
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
        if ($this->plainScalars && ! is_array($value) && ! is_object($value)) {
            $this->assertScalar($value, $path);

            return $path === [] ? $value : $this->keepChild($value, $inside);
        }

        $entered = [];

        try {
            $value = $this->object($value, $path, $depth, $entered);

            if ($path !== [] && $this->rules->ignoreWhen !== [] && $this->ignoredByCallback($value, $path)) {
                return $this->drop;
            }

            $rules = $this->rules->valuesFor($path);
            $transform = $rules['transform'] ?? null;

            if (is_callable($transform)) {
                $value = $this->object($transform(self::plain($value), Path::format($path)), $path, $depth, $entered);
            }

            $value = Scalars::apply($value, $rules, $path);

            if ($value instanceof Map || is_array($value)) {
                $value = $value instanceof Map
                    ? $this->container($value->items, true, $path, $depth + 1, $inside)
                    : $this->container($value, false, $path, $depth + 1, $inside);

                if (is_array($value)) {
                    $value = $this->listRules($value, $rules, $path);
                }
            } else {
                $this->assertScalar($value, $path);
            }
        } finally {
            foreach ($entered as $id) {
                unset($this->active[$id]);
            }
        }

        return $path === [] ? $value : $this->keepChild($value, $inside);
    }

    /**
     * Convert objects into plain data until the value is not a convertible object
     *
     * DateTimeInterface is kept for the date rules; stdClass becomes a Map so it stays a map.
     *
     * @param list<int|string> $path
     * @param list<int> $entered Objects entered here, released by the caller.
     */
    private function object(mixed $value, array $path, int $depth, array &$entered): mixed
    {
        while (is_object($value) && ! $value instanceof DateTimeInterface && ! $value instanceof Map && ! $value instanceof Date) {
            if ($value instanceof Closure || $value instanceof Generator) {
                break;
            }

            $id = spl_object_id($value);

            if (isset($this->active[$id])) {
                throw CircularReferenceException::of(Path::format($path), get_class($value));
            }

            if (++$depth > $this->maxDepth) {
                throw MaximumDepthExceededException::limit(Path::format($path), $this->maxDepth);
            }

            $this->active[$id] = $value;
            $entered[] = $id;

            $normalizer = $this->rules->normalizerFor($value);

            if ($normalizer !== null) {
                $value = $normalizer($value);
            } elseif ($value instanceof JsonSerializable) {
                $value = $value->jsonSerialize();
            } elseif ($value instanceof Traversable) {
                $value = self::iterate($value, $path);
            } elseif ($value instanceof stdClass) {
                $value = new Map(get_object_vars($value));
            } else {
                break;
            }
        }

        return $value;
    }

    /**
     * @param Traversable<mixed, mixed> $value
     * @param list<int|string> $path
     * @return array<int|string, mixed>
     */
    private static function iterate(Traversable $value, array $path): array
    {
        $items = [];

        foreach ($value as $key => $item) {
            if (! is_int($key) && ! is_string($key)) {
                throw UnsupportedValueException::at(Path::format($path), sprintf('A %s yielding a %s key cannot be fingerprinted', get_class($value), get_debug_type($key)));
            }

            if (array_key_exists($key, $items)) {
                throw UnsupportedValueException::at(Path::format($path), sprintf('A %s yielding the key "%s" twice cannot be fingerprinted', get_class($value), $key));
            }

            $items[$key] = $item;
        }

        return $items;
    }

    /**
     * @param list<int|string> $path
     */
    private function ignoredByCallback(mixed $value, array $path): bool
    {
        foreach ($this->rules->ignoreWhen as $callback) {
            $ignore = $callback(Path::format($path), self::plain($value));

            if (! is_bool($ignore)) {
                throw new ConfigurationException(sprintf(
                    'An ignoreWhen() callback must return a boolean, %s returned at path "%s".',
                    get_debug_type($ignore),
                    Path::format($path),
                ));
            }

            if ($ignore) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int|string, mixed> $value
     * @param list<int|string> $path
     * @return list<mixed>|Map
     */
    private function container(array $value, bool $isMap, array $path, int $depth, bool $inside): array|Map
    {
        if ($depth > $this->maxDepth) {
            throw MaximumDepthExceededException::limit(Path::format($path), $this->maxDepth);
        }

        $items = [];
        $only = $this->rules->only;
        $ignore = $this->rules->ignore;

        foreach ($value as $key => $child) {
            $childPath = $path;
            $childPath[] = $key;
            $childInside = $inside || ($only !== [] && Rules::anyMatches($only, $childPath));

            if (! $childInside && ! Rules::anyLeadsTo($only, $childPath)) {
                continue;
            }

            if ($ignore !== [] && Rules::anyMatches($ignore, $childPath)) {
                continue;
            }

            $child = $this->node($child, $childPath, $depth, $childInside);

            if ($child !== $this->drop) {
                $items[$key] = $child;
            }
        }

        return ! $isMap && array_is_list($value) ? array_values($items) : new Map($items);
    }

    /**
     * @param list<mixed> $value
     * @param array<string, mixed> $rules
     * @param list<int|string> $path
     * @return list<mixed>
     */
    private function listRules(array $value, array $rules, array $path): array
    {
        $order = $rules['order'] ?? null;
        $unique = $rules['unique'] ?? null;

        // Ordering first, so de-duplication keeps the first occurrence in the final order.
        if ($order instanceof ListOrder) {
            $value = $order->apply($value, Path::format($path));
        }

        if ($unique instanceof ListUnique) {
            $value = $unique->apply($value);
        }

        return $value;
    }

    private function keepChild(mixed $value, bool $inside): mixed
    {
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
     * @param list<int|string> $path
     */
    private function assertScalar(mixed $value, array $path): void
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value) || $value instanceof Date) {
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

    /**
     * What callbacks receive: a stdClass already converted shows up as its array of properties
     */
    private static function plain(mixed $value): mixed
    {
        return $value instanceof Map ? $value->items : $value;
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

        if ($value instanceof Generator) {
            return 'A generator';
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
