<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint;

use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Support\Canonicalizer;
use Pharaonic\Fingerprint\Support\Path;
use Pharaonic\Fingerprint\Support\Rules;

/**
 * Immutable fingerprint configuration bound to one value
 *
 * Every configuration method returns a new builder and leaves the current one unchanged.
 *
 * Paths use dot notation: "user.email", "items.*.price" ("*" matches any one key or list
 * index), with "\." and "\*" for a literal dot or star inside a key.
 *
 * Value rules such as transform() apply to every value until at() is called, and to the
 * values at the latest at() path afterwards. For the same kind of rule, the last one
 * declared for a path wins.
 */
final class FingerprintBuilder
{
    private const ALGORITHM = 'sha256';

    private Rules $rules;

    /** @var non-empty-list<string|null>|null */
    private ?array $scope = null;

    /**
     * @internal Use Fingerprint::for().
     */
    public function __construct(private mixed $value)
    {
        $this->rules = new Rules();
    }

    public function __clone()
    {
        $this->rules = clone $this->rules;
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

    /**
     * Leave out the values at these paths
     *
     * @param string|array<string> $paths
     */
    public function ignore(string|array $paths): self
    {
        $builder = clone $this;

        foreach (self::patterns(is_string($paths) ? [$paths] : $paths, 'ignore') as $pattern) {
            $builder->rules->ignore[] = $pattern;
        }

        return $builder;
    }

    /**
     * Keep only the values at these paths, their descendants, and the containers leading to them
     *
     * The order of the paths never matters. Calling only() again adds more paths.
     *
     * @param array<string> $paths
     */
    public function only(array $paths): self
    {
        if ($paths === []) {
            throw new ConfigurationException('only() needs at least one path.');
        }

        $builder = clone $this;

        foreach (self::patterns($paths, 'only') as $pattern) {
            $builder->rules->only[] = $pattern;
        }

        return $builder;
    }

    /**
     * Leave out every value for which the callback returns true
     *
     * The callback receives the value's escaped dot path and the value, before value rules apply.
     *
     * @param callable(string $path, mixed $value): bool $callback
     */
    public function ignoreWhen(callable $callback): self
    {
        $builder = clone $this;
        $builder->rules->ignoreWhen[] = $callback;

        return $builder;
    }

    /**
     * Leave out values that end up null
     */
    public function ignoreNulls(): self
    {
        $builder = clone $this;
        $builder->rules->ignoreNulls = true;

        return $builder;
    }

    /**
     * Leave out values that end up as an empty string
     */
    public function ignoreEmptyStrings(): self
    {
        $builder = clone $this;
        $builder->rules->ignoreEmptyStrings = true;

        return $builder;
    }

    /**
     * Leave out arrays that end up empty, including those emptied by other filters
     */
    public function ignoreEmptyArrays(): self
    {
        $builder = clone $this;
        $builder->rules->ignoreEmptyArrays = true;

        return $builder;
    }

    /**
     * Apply the following value rules to the values at this path
     */
    public function at(string $path): self
    {
        $builder = clone $this;
        $builder->scope = Path::parse($path);

        return $builder;
    }

    /**
     * Replace the value with the callback's result before it is fingerprinted
     *
     * The result is normalized like any other value, so it may be an array.
     *
     * @param callable(mixed $value, string $path): mixed $callback
     */
    public function transform(callable $callback): self
    {
        return $this->rule('transform', $callback);
    }

    public function make(): string
    {
        return hash(self::ALGORITHM, (new Canonicalizer(null, $this->rules))->canonicalize($this->value));
    }

    private function rule(string $kind, mixed $argument): self
    {
        $builder = clone $this;
        $builder->rules->values[] = [$this->scope, $kind, $argument];

        return $builder;
    }

    /**
     * @param array<mixed> $paths
     * @return list<non-empty-list<string|null>>
     */
    private static function patterns(array $paths, string $method): array
    {
        $patterns = [];

        foreach ($paths as $path) {
            if (! is_string($path)) {
                throw new ConfigurationException(sprintf('%s() paths must be strings, %s given.', $method, get_debug_type($path)));
            }

            $patterns[] = Path::parse($path);
        }

        return $patterns;
    }
}
