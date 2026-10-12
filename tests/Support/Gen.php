<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Tests\Support;

/**
 * Seeded random values for property tests (mt_srand() makes every run reproducible)
 */
final class Gen
{
    public static function value(int $depth = 4): mixed
    {
        $kind = mt_rand(0, $depth > 0 ? 7 : 5);

        switch ($kind) {
            case 0:
                return null;
            case 1:
                return (bool) mt_rand(0, 1);
            case 2:
                return mt_rand(-1000, 1000);
            case 3:
                return mt_rand(-100000, 100000) / 7;
            case 4:
            case 5:
                return self::string();
            case 6:
                return self::list($depth - 1);
            default:
                return self::map($depth - 1);
        }
    }

    public static function string(int $max = 6): string
    {
        $string = '';

        for ($i = mt_rand(0, $max); $i > 0; $i--) {
            $string .= chr(mt_rand(0, 255));
        }

        return $string;
    }

    /**
     * @return list<mixed>
     */
    public static function list(int $depth): array
    {
        $list = [];

        for ($i = mt_rand(0, 5); $i > 0; $i--) {
            $list[] = self::value($depth);
        }

        return $list;
    }

    /**
     * A map with at least one string key, so it never collapses into a list
     *
     * @return array<int|string, mixed>
     */
    public static function map(int $depth): array
    {
        $map = ['k' . mt_rand() => self::value($depth)];

        for ($i = mt_rand(0, 5); $i > 0; $i--) {
            $key = mt_rand(0, 1) ? mt_rand(-50, 50) : self::string(4);
            $map[$key] = self::value($depth);
        }

        return $map;
    }

    /**
     * The same value with every map's keys shuffled, recursively; lists keep their order
     */
    public static function shuffleMaps(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);
        $keys = array_keys($value);

        if (! $isList) {
            shuffle($keys);
        }

        $shuffled = [];

        foreach ($keys as $key) {
            $shuffled[$key] = self::shuffleMaps($value[$key]);
        }

        return $shuffled;
    }
}
