<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint;

use LogicException;
use Normalizer as IntlNormalizer;
use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Exceptions\MissingExtensionException;
use Pharaonic\Fingerprint\Support\Canonicalizer;
use Pharaonic\Fingerprint\Support\Digest;
use Pharaonic\Fingerprint\Support\ListOrder;
use Pharaonic\Fingerprint\Support\ListUnique;
use Pharaonic\Fingerprint\Support\Path;
use Pharaonic\Fingerprint\Support\Rules;
use Pharaonic\Fingerprint\Support\Scalars;

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
    private Rules $rules;

    private Digest $digest;

    private ?string $namespace = null;

    /** @var non-empty-list<string|null>|null */
    private ?array $scope = null;

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
     * Remove ASCII whitespace (space, tab, line feed, carriage return, vertical tab, form feed) from both ends of strings
     */
    public function trim(): self
    {
        return $this->rule('trim', true);
    }

    /**
     * Turn every run of ASCII whitespace in strings into one space, with none at either end
     */
    public function normalizeWhitespace(): self
    {
        return $this->rule('whitespace', true);
    }

    /**
     * Lowercase strings (UTF-8, through mbstring)
     */
    public function lower(): self
    {
        return $this->rule('case', 'lower');
    }

    /**
     * Uppercase strings (UTF-8, through mbstring)
     */
    public function upper(): self
    {
        return $this->rule('case', 'upper');
    }

    /**
     * Apply a Unicode normalization form to strings: NFC, NFD, NFKC or NFKD
     *
     * Needs ext-intl or symfony/polyfill-intl-normalizer.
     */
    public function unicode(string $form): self
    {
        if (! in_array($form, Scalars::UNICODE_FORMS, true)) {
            throw new ConfigurationException(sprintf('unicode() accepts NFC, NFD, NFKC or NFKD, "%s" given.', $form));
        }

        if (! class_exists(IntlNormalizer::class)) {
            throw new MissingExtensionException('unicode() needs ext-intl or symfony/polyfill-intl-normalizer.');
        }

        return $this->rule('unicode', $form);
    }

    /**
     * Round floats to a number of decimals, from their exact binary value: 0.285 becomes 0.28
     */
    public function precision(int $digits): self
    {
        if ($digits < 0 || $digits > 53) {
            throw new ConfigurationException(sprintf('precision() accepts 0 to 53 digits, %d given.', $digits));
        }

        return $this->rule('precision', $digits);
    }

    /**
     * Turn plain decimal strings into numbers: '1' becomes 1, '1.50' becomes 1.5
     *
     * Only an optional minus sign, digits without leading zeros, an optional fraction and an
     * optional exponent qualify; ' 1', '+1', '01', '.5' and '1.' stay strings. Integers that fit
     * become int, everything else float.
     */
    public function normalizeNumericStrings(): self
    {
        return $this->rule('numericStrings', true);
    }

    /**
     * Turn true, 1, '1' and 'true' into true, and false, 0, '0' and 'false' into false
     */
    public function normalizeBooleans(): self
    {
        return $this->rule('booleans', true);
    }

    /**
     * Truncate dates to a unit: microsecond (the default), second, minute, hour or day
     */
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

    /**
     * Separate fingerprints of identical data used for different purposes
     *
     * "invoice" and "order" fingerprints of the same data never match, and neither matches a
     * fingerprint without a namespace. A namespace is not a secret: use hmac() for that.
     */
    public function namespace(string $name): self
    {
        if ($name === '') {
            throw new ConfigurationException('A namespace cannot be empty.');
        }

        $builder = clone $this;
        $builder->namespace = $name;

        return $builder;
    }

    /**
     * Hash with another algorithm available to hash() (sha256 by default)
     *
     * Non-cryptographic algorithms such as crc32b, fnv1a64 or md5 are fine for change detection
     * but must not protect anything.
     */
    public function algorithm(string $algorithm): self
    {
        $builder = clone $this;
        $builder->digest = $this->digest->withAlgorithm($algorithm);

        return $builder;
    }

    /**
     * Sign with an HMAC secret instead of a plain hash
     *
     * The secret never appears in var_dump(), print_r() or exception messages, and the builder
     * cannot be serialized.
     */
    public function hmac(string $secret): self
    {
        $builder = clone $this;
        $builder->digest = $this->digest->withSecret($secret);

        return $builder;
    }

    /**
     * Lowercase hexadecimal output (the default)
     */
    public function hex(): self
    {
        return $this->encoding('hex');
    }

    /**
     * Raw binary output
     */
    public function binary(): self
    {
        return $this->encoding('binary');
    }

    /**
     * Standard base64 output, with padding
     */
    public function base64(): self
    {
        return $this->encoding('base64');
    }

    /**
     * URL-safe base64 output ("-" and "_"), without padding
     */
    public function base64Url(): self
    {
        return $this->encoding('base64url');
    }

    public function make(): string
    {
        return $this->digest->hash($this->canonical());
    }

    /**
     * Whether the value matches an expected fingerprint, compared in constant time
     *
     * Hex fingerprints are compared case-insensitively, other encodings exactly.
     */
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
        return (new Canonicalizer($this->namespace, $this->rules))->canonicalize($this->value);
    }

    private function encoding(string $encoding): self
    {
        $builder = clone $this;
        $builder->digest = $this->digest->withEncoding($encoding);

        return $builder;
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
