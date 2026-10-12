<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint;

use Pharaonic\Fingerprint\Support\Canonicalizer;

/**
 * Immutable fingerprint configuration bound to one value
 *
 * Every configuration method returns a new builder and leaves the current one unchanged.
 */
final class FingerprintBuilder
{
    private const ALGORITHM = 'sha256';

    /**
     * @internal Use Fingerprint::for().
     */
    public function __construct(private mixed $value)
    {
    }

    /**
     * The same configuration bound to another value
     */
    public function for(mixed $value): self
    {
        $builder = clone $this;
        $builder->value = $value;

        return $builder;
    }

    public function make(): string
    {
        return hash(self::ALGORITHM, (new Canonicalizer())->canonicalize($this->value));
    }
}
