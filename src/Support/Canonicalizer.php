<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

/**
 * Produces the canonical document of a value: normalize, then encode
 *
 * @internal
 */
final class Canonicalizer
{
    public const DEFAULT_MAX_DEPTH = 512;

    private Rules $rules;

    public function __construct(
        private ?string $namespace = null,
        ?Rules $rules = null,
        private int $maxDepth = self::DEFAULT_MAX_DEPTH,
    ) {
        $this->rules = $rules ?? new Rules();
    }

    public function canonicalize(mixed $value): string
    {
        $tree = (new Normalizer($this->rules, $this->maxDepth))->normalize($value);

        return Encoder::document($this->namespace, Encoder::encode($tree));
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
}
