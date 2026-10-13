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
    public const int DEFAULT_MAX_DEPTH = 512;

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
        return $this->document($this->normalize($value));
    }

    /**
     * The normalized tree of a value
     */
    public function normalize(mixed $value): mixed
    {
        return (new Normalizer($this->rules, $this->maxDepth))->normalize($value);
    }

    /**
     * The canonical document of a normalized tree
     */
    public function document(mixed $tree): string
    {
        return Encoder::document($this->namespace, Encoder::encode($tree));
    }
}
