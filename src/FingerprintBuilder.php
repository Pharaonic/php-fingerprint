<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint;

use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Support\Canonicalizer;
use Pharaonic\Fingerprint\Support\ListOrder;
use Pharaonic\Fingerprint\Support\ListUnique;
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

    /**
     * Fingerprint lists regardless of element order
     *
     * Elements are ordered by their canonical bytes, so lists holding the same elements
     * (duplicates included) give the same fingerprint. Has no effect on maps and scalars.
     */
    public function unordered(): self
    {
        return $this->rule('order', ListOrder::unordered());
    }

    /**
     * Order list elements by one or more fields before fingerprinting
     *
     * Accepts a field ('id'), a list of fields (['category', 'id']), or fields with a
     * direction (['category' => 'asc', 'priority' => 'desc']). Fields may be dot paths
     * inside each element, without wildcards. Ties keep their input order.
     *
     * @param string|array<int|string, string> $fields
     */
    public function sortBy(string|array $fields): self
    {
        return $this->rule('order', ListOrder::by(self::sortFields(is_string($fields) ? [$fields] : $fields)));
    }

    /**
     * Order list elements with a comparator before fingerprinting
     *
     * The comparator receives two elements as plain PHP values and returns an integer, like usort().
     *
     * @param callable(mixed $a, mixed $b): int $comparator
     */
    public function sortUsing(callable $comparator): self
    {
        return $this->rule('order', ListOrder::using($comparator));
    }

    /**
     * Remove repeated list elements, keeping the first occurrence after any ordering rule
     *
     * Elements are compared strictly: 1 and '1' are different.
     */
    public function unique(): self
    {
        return $this->rule('unique', new ListUnique());
    }

    /**
     * Remove list elements that repeat a field's value, keeping the first occurrence after any ordering rule
     */
    public function uniqueBy(string $field): self
    {
        return $this->rule('unique', new ListUnique(self::field($field, 'uniqueBy')));
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
     * @param array<mixed> $fields
     * @return non-empty-list<array{non-empty-list<string>, bool}>
     */
    private static function sortFields(array $fields): array
    {
        $parsed = [];

        foreach ($fields as $field => $direction) {
            if (is_int($field)) {
                [$field, $direction] = [$direction, 'asc'];
            }

            if (! is_string($field) || ! in_array($direction, ['asc', 'desc'], true)) {
                throw new ConfigurationException('sortBy() fields must be strings, with the direction "asc" or "desc".');
            }

            $parsed[] = [self::field($field, 'sortBy'), $direction === 'desc'];
        }

        if ($parsed === []) {
            throw new ConfigurationException('sortBy() needs at least one field.');
        }

        return $parsed;
    }

    /**
     * @return non-empty-list<string>
     */
    private static function field(string $field, string $method): array
    {
        $segments = [];

        foreach (Path::parse($field) as $segment) {
            if ($segment === null) {
                throw new ConfigurationException(sprintf('%s() fields cannot contain wildcards, "%s" given.', $method, $field));
            }

            $segments[] = $segment;
        }

        return $segments;
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
