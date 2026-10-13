<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

use Closure;
use Pharaonic\Fingerprint\Exceptions\ConfigurationException;

/**
 * How a list is ordered before it is fingerprinted: unordered(), sortBy() or sortUsing()
 *
 * sortBy() uses one fixed order across types: missing < null < bool < number < string <
 * list or map. Numbers compare by value (an integer before an equal float), strings byte by
 * byte, lists and maps by their canonical bytes. Ties keep their input order.
 *
 * @internal
 */
final readonly class ListOrder
{
    /**
     * @param list<array{non-empty-list<string>, bool}> $fields Field paths, and whether each is descending.
     * @param (Closure(mixed, mixed): mixed)|null $comparator
     */
    private function __construct(private array $fields = [], private ?Closure $comparator = null) {}

    public static function unordered(): self
    {
        return new self();
    }

    /**
     * @param non-empty-list<array{non-empty-list<string>, bool}> $fields
     */
    public static function by(array $fields): self
    {
        return new self($fields);
    }

    /**
     * @param callable(mixed, mixed): mixed $comparator
     */
    public static function using(callable $comparator): self
    {
        return new self([], $comparator(...));
    }

    /**
     * @param list<mixed> $items
     * @return list<mixed>
     */
    public function apply(array $items, string $path): array
    {
        if ($this->comparator !== null) {
            return $this->sortUsing($items, $this->comparator, $path);
        }

        if ($this->fields === []) {
            $keyed = array_map(static fn(mixed $item): array => [Encoder::encode($item), $item], $items);
            usort($keyed, static fn(array $a, array $b): int => strcmp($a[0], $b[0]));

            return array_column($keyed, 1);
        }

        $keyed = [];

        foreach ($items as $item) {
            $values = [];

            foreach ($this->fields as [$field]) {
                $values[] = Tree::field($item, $field);
            }

            $keyed[] = [$values, $item];
        }

        usort($keyed, function (array $a, array $b): int {
            foreach ($this->fields as $i => [, $descending]) {
                $result = self::compare($a[0][$i], $b[0][$i]);

                if ($result !== 0) {
                    return $descending ? -$result : $result;
                }
            }

            return 0;
        });

        return array_column($keyed, 1);
    }

    /**
     * @param list<mixed> $items
     * @param callable(mixed, mixed): mixed $comparator
     * @return list<mixed>
     */
    private function sortUsing(array $items, callable $comparator, string $path): array
    {
        $keyed = array_map(static fn(mixed $item): array => [Tree::toPhp($item), $item], $items);

        usort($keyed, static function (array $a, array $b) use ($comparator, $path): int {
            $result = $comparator($a[0], $b[0]);

            if (! is_int($result)) {
                throw new ConfigurationException(sprintf(
                    'A sortUsing() comparator must return an integer, %s returned at path "%s".',
                    get_debug_type($result),
                    $path,
                ));
            }

            return $result;
        });

        return array_column($keyed, 1);
    }

    private static function compare(mixed $a, mixed $b): int
    {
        $rankA = self::rank($a);
        $rankB = self::rank($b);

        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }

        if (is_bool($a) && is_bool($b)) {
            return $a <=> $b;
        }

        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return ($a <=> $b) ?: (is_float($a) <=> is_float($b));
        }

        if (is_string($a) && is_string($b)) {
            return strcmp($a, $b) <=> 0;
        }

        if ($rankA === 5) {
            return strcmp(Encoder::encode($a), Encoder::encode($b)) <=> 0;
        }

        return 0;
    }

    private static function rank(mixed $value): int
    {
        if ($value === Tree::missing()) {
            return 0;
        }

        if ($value === null) {
            return 1;
        }

        if (is_bool($value)) {
            return 2;
        }

        if (is_int($value) || is_float($value)) {
            return 3;
        }

        return is_string($value) ? 4 : 5;
    }
}
