<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

use BackedEnum;
use LogicException;
use UnitEnum;

/**
 * Canonical format v1 tokens
 *
 * Every token frames itself (a type marker, then a fixed width, a length or a terminator),
 * so no two different values can produce the same bytes once tokens are joined.
 *
 * ```text
 * document = "fp1;" (null | string) value       namespace first, then the value
 * null     = "n"
 * bool     = "b0" | "b1"
 * int      = "i" <decimal> ";"
 * float    = "d" <16 lowercase hex digits of pack('E')>
 * string   = "s" <byte length> ":" <bytes>
 * list     = "l" <count> "[" value* "]"
 * map      = "m" <count> "{" (key value)* "}"    keys sorted by their token bytes
 * instant  = "t" <Y-m-d\TH:i:s.u> "Z"             a date as a UTC instant
 * zoned    = "z" <length> ":" <Y-m-d\TH:i:s.u> " " <timezone name>   keepTimezone()
 * enum     = "e" string string (int | string | null)   class, case name, backed value (null when pure)
 * ```
 *
 * Changing any byte produced here changes stored fingerprints: it needs a new format version.
 *
 * @internal
 */
final class Encoder
{
    public const VERSION = 'v1';

    public static function document(?string $namespace, string $value): string
    {
        return 'fp1;' . ($namespace === null ? self::null() : self::string($namespace)) . $value;
    }

    /**
     * Encode a normalized tree: scalars, lists (plain arrays) and maps (Map)
     */
    public static function encode(mixed $node): string
    {
        if ($node === null) {
            return self::null();
        }

        if (is_bool($node)) {
            return self::bool($node);
        }

        if (is_int($node)) {
            return self::int($node);
        }

        if (is_float($node)) {
            return self::float($node);
        }

        if (is_string($node)) {
            return self::string($node);
        }

        if ($node instanceof Date) {
            return self::date($node);
        }

        if ($node instanceof UnitEnum) {
            return self::enum($node);
        }

        if ($node instanceof Map) {
            $entries = [];

            foreach ($node->items as $key => $item) {
                $entries[self::key($key)] = self::encode($item);
            }

            return self::map($entries);
        }

        if (is_array($node)) {
            return self::list(array_map(self::encode(...), array_values($node)));
        }

        throw new LogicException(sprintf('A normalized tree cannot hold %s.', get_debug_type($node)));
    }

    public static function date(Date $date): string
    {
        if ($date->zone === null) {
            return 't' . $date->time . 'Z';
        }

        $local = $date->time . ' ' . $date->zone;

        return 'z' . strlen($local) . ':' . $local;
    }

    /**
     * The class takes part, so cases of different enums never collide, even with equal backed values
     */
    public static function enum(UnitEnum $case): string
    {
        $value = $case instanceof BackedEnum ? $case->value : null;

        return 'e' . self::string(get_class($case)) . self::string($case->name)
            . ($value === null ? self::null() : (is_int($value) ? self::int($value) : self::string($value)));
    }

    public static function null(): string
    {
        return 'n';
    }

    public static function bool(bool $value): string
    {
        return $value ? 'b1' : 'b0';
    }

    public static function int(int $value): string
    {
        return 'i' . $value . ';';
    }

    /**
     * The exact IEEE-754 bits, so the token never depends on ini precision or the PHP version
     *
     * Negative zero is folded into zero. NAN must be rejected before reaching here.
     */
    public static function float(float $value): string
    {
        if ($value == 0.0) {
            $value = 0.0;
        }

        return 'd' . bin2hex(pack('E', $value));
    }

    public static function string(string $value): string
    {
        return 's' . strlen($value) . ':' . $value;
    }

    /**
     * @param list<string> $items Encoded values, in list order.
     */
    public static function list(array $items): string
    {
        return 'l' . count($items) . '[' . implode('', $items) . ']';
    }

    /**
     * @param array<string, string> $entries Encoded values keyed by their encoded key.
     */
    public static function map(array $entries): string
    {
        // Binary comparison of the key tokens: never locale-aware, and int keys stay apart from string keys.
        uksort($entries, strcmp(...));

        $body = '';

        foreach ($entries as $key => $value) {
            $body .= $key . $value;
        }

        return 'm' . count($entries) . '{' . $body . '}';
    }

    public static function key(int|string $key): string
    {
        return is_int($key) ? self::int($key) : self::string($key);
    }
}
