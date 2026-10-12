<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

/**
 * Filters and value rules collected by a builder
 *
 * Builders clone this object before changing it, so a builder never shares rules with another.
 *
 * @internal
 */
final class Rules
{
    /** @var list<non-empty-list<string|null>> */
    public array $ignore = [];

    /** @var list<non-empty-list<string|null>> */
    public array $only = [];

    /** @var list<callable(string, mixed): mixed> */
    public array $ignoreWhen = [];

    public bool $ignoreNulls = false;

    public bool $ignoreEmptyStrings = false;

    public bool $ignoreEmptyArrays = false;

    /**
     * Value rules in declaration order; a null pattern applies everywhere
     *
     * @var list<array{non-empty-list<string|null>|null, string, mixed}>
     */
    public array $values = [];

    /**
     * The value rules that apply to a path, by kind: the last declared rule of each kind wins
     *
     * @param list<int|string> $path
     * @return array<string, mixed>
     */
    public function valuesFor(array $path): array
    {
        $rules = [];

        foreach ($this->values as [$pattern, $kind, $argument]) {
            if ($pattern === null || Path::matches($pattern, $path)) {
                $rules[$kind] = $argument;
            }
        }

        return $rules;
    }

    /**
     * @param list<non-empty-list<string|null>> $patterns
     * @param list<int|string> $path
     */
    public static function anyMatches(array $patterns, array $path): bool
    {
        foreach ($patterns as $pattern) {
            if (Path::matches($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<non-empty-list<string|null>> $patterns
     * @param list<int|string> $path
     */
    public static function anyLeadsTo(array $patterns, array $path): bool
    {
        foreach ($patterns as $pattern) {
            if (Path::leadsTo($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
