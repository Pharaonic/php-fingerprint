<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

use stdClass;

/**
 * Helpers for normalized trees: scalars, lists (plain arrays) and maps (Map)
 *
 * @internal
 */
final class Tree
{
    private static ?stdClass $missing = null;

    /**
     * The marker returned by field() for a missing field
     */
    public static function missing(): stdClass
    {
        return self::$missing ??= new stdClass();
    }

    /**
     * The node at a field path inside an element, or missing()
     *
     * @param non-empty-list<string> $field
     */
    public static function field(mixed $node, array $field): mixed
    {
        foreach ($field as $segment) {
            $items = $node instanceof Map ? $node->items : $node;

            if (! is_array($items) || ! array_key_exists($segment, $items)) {
                return self::missing();
            }

            $node = $items[$segment];
        }

        return $node;
    }

    /**
     * Plain PHP values for user callbacks and debugging
     */
    public static function toPhp(mixed $node): mixed
    {
        if ($node instanceof Map) {
            return array_map([self::class, 'toPhp'], $node->items);
        }

        if (is_array($node)) {
            return array_map([self::class, 'toPhp'], $node);
        }

        return $node;
    }
}
