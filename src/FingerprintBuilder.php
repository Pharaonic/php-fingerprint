<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint;

use LogicException;
use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Concerns\HasDigest;
use Pharaonic\Fingerprint\Concerns\HasValueRules;
use Pharaonic\Fingerprint\Support\Canonicalizer;
use Pharaonic\Fingerprint\Support\Digest;
use Pharaonic\Fingerprint\Support\Encoder;
use Pharaonic\Fingerprint\Support\ListOrder;
use Pharaonic\Fingerprint\Support\ListUnique;
use Pharaonic\Fingerprint\Support\Path;
use Pharaonic\Fingerprint\Support\Rules;
use Pharaonic\Fingerprint\Support\Scalars;
use Pharaonic\Fingerprint\Support\Tree;
use NoDiscard;

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
    use HasDigest;
    use HasValueRules;

    private ?string $namespace = null;

    private int $maxDepth = Canonicalizer::DEFAULT_MAX_DEPTH;

    /**
     * @internal Use Fingerprint::for().
     */
    public function __construct(private mixed $value)
    {
        $this->rules = new Rules();
        $this->digest = new Digest();
    }

    public function __clone()
    {
        $this->rules = clone $this->rules;
    }

    /**
     * The same configuration bound to another value
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function for(mixed $value): self
    {
        return clone($this, ['value' => $value]);
    }

    /**
     * Leave out the values at these paths
     *
     * @param string|array<string> $paths
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
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
    #[NoDiscard('the builder is immutable, so use the one it returns')]
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
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function ignoreWhen(callable $callback): self
    {
        $builder = clone $this;
        $builder->rules->ignoreWhen[] = $callback;

        return $builder;
    }

    /**
     * Leave out values that end up null
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function ignoreNulls(): self
    {
        $builder = clone $this;
        $builder->rules->ignoreNulls = true;

        return $builder;
    }

    /**
     * Leave out values that end up as an empty string
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function ignoreEmptyStrings(): self
    {
        $builder = clone $this;
        $builder->rules->ignoreEmptyStrings = true;

        return $builder;
    }

    /**
     * Leave out arrays that end up empty, including those emptied by other filters
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function ignoreEmptyArrays(): self
    {
        $builder = clone $this;
        $builder->rules->ignoreEmptyArrays = true;

        return $builder;
    }

    /**
     * Convert objects of a class, or implementing an interface, with a callback
     *
     * The callback's result is normalized like any other value. An exact class match wins,
     * then the first matching class or interface in declaration order. Applies everywhere,
     * whatever at() scope is active. Use it to opt Stringable objects in:
     * normalize(Stringable::class, fn ($value) => (string) $value).
     *
     * @param class-string $class
     * @param callable(object $value): mixed $normalizer
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function normalize(string $class, callable $normalizer): self
    {
        if (! class_exists($class) && ! interface_exists($class)) {
            throw new ConfigurationException(sprintf('normalize() needs an existing class or interface, "%s" given.', $class));
        }

        $builder = clone $this;
        $builder->rules->normalizers[] = [$class, $normalizer];

        return $builder;
    }

    /**
     * Turn plain decimal strings into numbers: '1' becomes 1, '1.50' becomes 1.5
     *
     * Only an optional minus sign, digits without leading zeros, an optional fraction and an
     * optional exponent qualify; ' 1', '+1', '01', '.5' and '1.' stay strings. Integers that fit
     * become int, everything else float.
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function normalizeNumericStrings(): self
    {
        return $this->rule('numericStrings', true);
    }

    /**
     * Turn true, 1, '1' and 'true' into true, and false, 0, '0' and 'false' into false
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function normalizeBooleans(): self
    {
        return $this->rule('booleans', true);
    }

    /**
     * Truncate dates to a unit: microsecond (the default), second, minute, hour or day
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function datePrecision(string $unit): self
    {
        if (! in_array($unit, Scalars::DATE_UNITS, true)) {
            throw new ConfigurationException(sprintf('datePrecision() accepts microsecond, second, minute, hour or day, "%s" given.', $unit));
        }

        return $this->rule('datePrecision', $unit);
    }

    /**
     * Fingerprint dates by their local time and timezone name instead of their UTC instant
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function keepTimezone(): self
    {
        return $this->rule('keepTimezone', true);
    }

    /**
     * Fingerprint lists regardless of element order
     *
     * Elements are ordered by their canonical bytes, so lists holding the same elements
     * (duplicates included) give the same fingerprint. Has no effect on maps and scalars.
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
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
    #[NoDiscard('the builder is immutable, so use the one it returns')]
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
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function sortUsing(callable $comparator): self
    {
        return $this->rule('order', ListOrder::using($comparator));
    }

    /**
     * Remove repeated list elements, keeping the first occurrence after any ordering rule
     *
     * Elements are compared strictly: 1 and '1' are different.
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function unique(): self
    {
        return $this->rule('unique', new ListUnique());
    }

    /**
     * Remove list elements that repeat a field's value, keeping the first occurrence after any ordering rule
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function uniqueBy(string $field): self
    {
        return $this->rule('unique', new ListUnique(self::field($field, 'uniqueBy')));
    }

    /**
     * How deeply arrays and objects may nest (512 by default, like json_decode())
     *
     * Deeper values throw a MaximumDepthExceededException instead of exhausting the stack;
     * self-referencing arrays hit this limit too.
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function maxDepth(int $depth): self
    {
        if ($depth < 1) {
            throw new ConfigurationException(sprintf('maxDepth() must be at least 1, %d given.', $depth));
        }

        return clone($this, ['maxDepth' => $depth]);
    }

    /**
     * Separate fingerprints of identical data used for different purposes
     *
     * "invoice" and "order" fingerprints of the same data never match, and neither matches a
     * fingerprint without a namespace. A namespace is not a secret: use hmac() for that.
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function namespace(string $name): self
    {
        if ($name === '') {
            throw new ConfigurationException('A namespace cannot be empty.');
        }

        return clone($this, ['namespace' => $name]);
    }

    #[NoDiscard]
    public function make(): string
    {
        return $this->digest->hash($this->canonical());
    }

    /**
     * The fingerprint with its canonical bytes, normalized value and options, for debugging
     */
    #[NoDiscard]
    public function result(): FingerprintResult
    {
        $canonicalizer = $this->canonicalizer();
        $tree = $canonicalizer->normalize($this->value);
        $canonical = $canonicalizer->document($tree);

        return new FingerprintResult(
            $this->digest->hash($canonical),
            $this->digest->algorithm(),
            $this->digest->encoding(),
            $this->digest->isHmac(),
            $this->namespace,
            Encoder::VERSION,
            $canonical,
            Tree::toPhp($tree),
        );
    }

    /**
     * Whether the value matches an expected fingerprint, compared in constant time
     *
     * Hex fingerprints are compared case-insensitively, other encodings exactly.
     */
    #[NoDiscard]
    public function verify(string $expected): bool
    {
        return $this->digest->verify($this->canonical(), $expected);
    }

    /**
     * @return array{algorithm: string, encoding: string, hmac: bool, namespace: string|null}
     */
    public function __debugInfo(): array
    {
        return $this->digest->describe() + ['namespace' => $this->namespace];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new LogicException('A fingerprint builder cannot be serialized because it may hold an HMAC secret.');
    }

    private function canonical(): string
    {
        return $this->canonicalizer()->canonicalize($this->value);
    }

    private function canonicalizer(): Canonicalizer
    {
        return new Canonicalizer($this->namespace, $this->rules, $this->maxDepth);
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
