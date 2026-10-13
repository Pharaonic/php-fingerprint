<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint;

use ArrayAccess;
use BackedEnum;
use Closure;
use JsonSerializable;
use LogicException;
use Pharaonic\Fingerprint\Concerns\HasDigest;
use Pharaonic\Fingerprint\Concerns\HasValueRules;
use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Exceptions\MissingFieldException;
use Pharaonic\Fingerprint\Exceptions\UnsupportedValueException;
use Pharaonic\Fingerprint\Support\Digest;
use Pharaonic\Fingerprint\Support\Path;
use Pharaonic\Fingerprint\Support\Rules;
use Pharaonic\Fingerprint\Support\Scalars;
use UnitEnum;
use NoDiscard;

/**
 * Immutable ordered signature of selected payload fields, for protocols where field order matters
 *
 * Unlike a fingerprint, nothing is sorted: the fields are taken exactly in the order fields()
 * declares, turned into strings, joined, then hashed or signed with HMAC.
 *
 * Field values become strings this way: strings as they are, integers in decimal, booleans
 * as "true" or "false". Floats need precision() on their field, and arrays, objects and dates
 * need a transform() that returns one of those types. A missing field or a null value throws
 * unless onMissing() or onNull() chooses otherwise.
 */
final class SignatureBuilder
{
    use HasDigest;
    use HasValueRules;

    private const array STRATEGIES = ['throw', 'empty', 'null', 'skip'];

    /** @var list<array{string, non-empty-list<string>}> Output key and path of each field, in order */
    private array $fields = [];

    private bool $keyValue = false;

    private string $separator = '';

    private string $keyValueSeparator = '=';

    /** "throw", "empty", "null", "skip", or a closure whose result is checked when used */
    private string|Closure $onMissing = 'throw';

    private string|Closure $onNull = 'throw';

    /**
     * @internal Use Fingerprint::signature().
     *
     * @param array<mixed>|object $payload
     */
    public function __construct(private array|object $payload)
    {
        $this->rules = new Rules();
        $this->digest = new Digest();
    }

    public function __clone()
    {
        $this->rules = clone $this->rules;
    }

    /**
     * The fields to sign, in exactly this order
     *
     * Fields are dot paths without wildcards. A string key renames the field in keyValue()
     * output: ['amount' => 'order.amount_cents']. Calling fields() again replaces the list.
     *
     * @param array<int|string, string> $fields
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function fields(array $fields): self
    {
        return clone($this, ['fields' => self::parseFields($fields)]);
    }

    /**
     * Join the values only: value1 . separator . value2 (the default)
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function valuesOnly(): self
    {
        return clone($this, ['keyValue' => false]);
    }

    /**
     * Join key-value pairs: key1 . keyValueSeparator . value1 . separator . key2 ...
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function keyValue(): self
    {
        return clone($this, ['keyValue' => true]);
    }

    /**
     * The string placed between fields ("" by default)
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function separator(string $separator): self
    {
        return clone($this, ['separator' => $separator]);
    }

    /**
     * The string placed between a key and its value in keyValue() output ("=" by default)
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function keyValueSeparator(string $separator): self
    {
        return clone($this, ['keyValueSeparator' => $separator]);
    }

    /**
     * What to do when a field is missing: "throw" (the default), "empty" (an empty string),
     * "null" (the string "null"), "skip" (leave the field out), or a closure that receives the
     * field path and returns the string to use, or null to skip the field
     *
     * @param string|Closure(string $field): ?string $strategy
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function onMissing(string|Closure $strategy): self
    {
        return clone($this, ['onMissing' => self::strategy($strategy, 'onMissing')]);
    }

    /**
     * What to do when a field holds null, with the same choices as onMissing(); "throw" by default
     *
     * @param string|Closure(string $field): ?string $strategy
     */
    #[NoDiscard('the builder is immutable, so use the one it returns')]
    public function onNull(string|Closure $strategy): self
    {
        return clone($this, ['onNull' => self::strategy($strategy, 'onNull')]);
    }

    /**
     * The exact string that is hashed or signed
     */
    #[NoDiscard]
    public function message(): string
    {
        if ($this->fields === []) {
            throw new ConfigurationException('A signature needs at least one field: call fields().');
        }

        $parts = [];

        foreach ($this->fields as [$key, $path]) {
            $field = Path::format($path);
            [$found, $value] = self::lookup($this->payload, $path);

            if (! $found) {
                $string = $this->resolve($this->onMissing, $field, static fn() => MissingFieldException::field($field));
            } elseif ($value === null) {
                $string = $this->resolve($this->onNull, $field, static fn() => new UnsupportedValueException(sprintf(
                    'The field "%s" is null; choose how to sign it with onNull().',
                    $field,
                )));
            } else {
                $string = $this->stringify($value, $path, $field);
            }

            if ($string !== null) {
                $parts[] = $this->keyValue ? $key . $this->keyValueSeparator . $string : $string;
            }
        }

        return implode($this->separator, $parts);
    }

    #[NoDiscard]
    public function make(): string
    {
        return $this->digest->hash($this->message());
    }

    /**
     * Whether the payload matches an expected signature, compared in constant time
     *
     * Hex signatures are compared case-insensitively, other encodings exactly.
     */
    #[NoDiscard]
    public function verify(string $expected): bool
    {
        return $this->digest->verify($this->message(), $expected);
    }

    /**
     * @return array{algorithm: string, encoding: string, hmac: bool, fields: list<string>}
     */
    public function __debugInfo(): array
    {
        return $this->digest->describe() + ['fields' => array_map(static fn(array $field): string => Path::format($field[1]), $this->fields)];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new LogicException('A signature builder cannot be serialized because it may hold an HMAC secret.');
    }

    /**
     * @param list<string> $path
     */
    private function stringify(mixed $value, array $path, string $field): string
    {
        $rules = $this->rules->valuesFor($path);
        $transform = $rules['transform'] ?? null;

        if (is_callable($transform)) {
            $value = $transform($value, $field);
        }

        $value = Scalars::apply($value, $rules, $field);

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if (is_float($value) && is_int($rules['precision'] ?? null) && is_finite($value)) {
            return sprintf('%.' . $rules['precision'] . 'F', $value);
        }

        $hint = is_float($value) ? 'set precision() for it' : ($value instanceof UnitEnum ? 'use enumsByName() or transform()' : 'convert it with transform()');

        throw new UnsupportedValueException(sprintf('The field "%s" holds %s; %s.', $field, get_debug_type($value), $hint));
    }

    /**
     * @param Closure(): \Throwable $exception
     */
    private function resolve(string|Closure $strategy, string $field, Closure $exception): ?string
    {
        if ($strategy instanceof Closure) {
            $string = $strategy($field);

            if ($string !== null && ! is_string($string)) {
                throw new ConfigurationException(sprintf('A field strategy must return a string or null, %s returned for "%s".', get_debug_type($string), $field));
            }

            return $string;
        }

        switch ($strategy) {
            case 'empty':
                return '';
            case 'null':
                return 'null';
            case 'skip':
                return null;
            default:
                throw $exception();
        }
    }

    /**
     * @param list<string> $path
     * @return array{bool, mixed} Whether the field exists, and its value.
     */
    private static function lookup(mixed $node, array $path): array
    {
        foreach ($path as $segment) {
            if ($node instanceof JsonSerializable) {
                $node = $node->jsonSerialize();
            }

            if (is_array($node) && array_key_exists($segment, $node)) {
                $node = $node[$segment];
            } elseif ($node instanceof ArrayAccess && $node->offsetExists($segment)) {
                $node = $node[$segment];
            } elseif (is_object($node) && array_key_exists($segment, get_object_vars($node))) {
                $node = get_object_vars($node)[$segment];
            } else {
                return [false, null];
            }
        }

        return [true, $node];
    }

    /**
     * @param array<mixed> $fields
     * @return list<array{string, non-empty-list<string>}>
     */
    private static function parseFields(array $fields): array
    {
        $parsed = [];

        foreach ($fields as $key => $field) {
            if (! is_string($field)) {
                throw new ConfigurationException(sprintf('fields() paths must be strings, %s given.', get_debug_type($field)));
            }

            $segments = [];

            foreach (Path::parse($field) as $segment) {
                if ($segment === null) {
                    throw new ConfigurationException(sprintf('fields() paths cannot contain wildcards, "%s" given.', $field));
                }

                $segments[] = $segment;
            }

            $parsed[] = [is_string($key) ? $key : $field, $segments];
        }

        return $parsed;
    }

    private static function strategy(string|Closure $strategy, string $method): string|Closure
    {
        if (is_string($strategy) && ! in_array($strategy, self::STRATEGIES, true)) {
            throw new ConfigurationException(sprintf('%s() accepts "throw", "empty", "null", "skip" or a closure, "%s" given.', $method, $strategy));
        }

        return $strategy;
    }
}
