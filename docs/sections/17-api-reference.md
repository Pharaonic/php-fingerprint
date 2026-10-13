## API Reference

All classes live in the `Pharaonic\Fingerprint` namespace. Builders are immutable: every method that configures one returns a new builder. Every builder method and every method of `Fingerprint` has `#[\NoDiscard]`, so PHP warns when its result is thrown away.

### Fingerprint

| Method | Description | Returns |
| --- | --- | --- |
| `make(mixed $value)` | SHA-256 hex fingerprint of a value | `string` |
| `verify(mixed $value, string $expected)` | Constant-time check against a SHA-256 hex fingerprint | `bool` |
| `for(mixed $value)` | Starts a configurable fingerprint | `FingerprintBuilder` |
| `signature(array\|object $payload)` | Starts an ordered signature | `SignatureBuilder` |
| `file(string $path)` | Starts a fingerprint of a file's raw bytes | `DigestBuilder` |
| `stream(resource $stream)` | Starts a fingerprint of a stream's raw bytes | `DigestBuilder` |

### FingerprintBuilder

| Method | Description |
| --- | --- |
| `for(mixed $value)` | The same configuration bound to another value |
| `ignore(string\|array $paths)` | Leaves out the values at these paths |
| `only(array $paths)` | Keeps only the values at these paths |
| `ignoreWhen(callable $callback)` | Leaves out values for which `fn (string $path, mixed $value): bool` returns `true` |
| `ignoreNulls()` | Leaves out values that end up `null` |
| `ignoreEmptyStrings()` | Leaves out values that end up `''` |
| `ignoreEmptyArrays()` | Leaves out arrays that end up empty |
| `normalize(string $class, callable $normalizer)` | Converts objects of a class or interface |
| `at(string $path)` | Scopes the following value rules to a path |
| `transform(callable $callback)` | Replaces values with `fn (mixed $value, string $path): mixed` |
| `enumsByName()` / `enumsByValue()` | Uses an enum case's name or backed value instead of the case |
| `trim()` | Trims ASCII whitespace from strings |
| `normalizeWhitespace()` | Collapses ASCII whitespace runs in strings |
| `lower()` / `upper()` | Changes the case of strings (UTF-8) |
| `unicode(string $form)` | Applies NFC, NFD, NFKC or NFKD |
| `precision(int $digits)` | Rounds floats to 0 to 53 decimals |
| `normalizeNumericStrings()` | Turns plain decimal strings into numbers |
| `normalizeBooleans()` | Turns `1`, `'1'`, `'true'`, `0`, `'0'`, `'false'` into booleans |
| `datePrecision(string $unit)` | Truncates dates to `microsecond`, `second`, `minute`, `hour` or `day` |
| `keepTimezone()` | Compares dates by local time and timezone |
| `unordered()` | Ignores list order |
| `sortBy(string\|array $fields)` | Orders list elements by fields |
| `sortUsing(callable $comparator)` | Orders list elements with a comparator |
| `unique()` | Removes repeated list elements |
| `uniqueBy(string $field)` | Removes list elements that repeat a field |
| `maxDepth(int $depth)` | Changes the nesting limit (512) |
| `namespace(string $name)` | Separates fingerprints by purpose |
| `algorithm(string $algorithm)` | Changes the hash algorithm (`sha256`) |
| `hmac(string $secret)` | Signs with an HMAC secret |
| `hex()` / `binary()` / `base64()` / `base64Url()` | Chooses the output encoding (`hex`) |
| `make(): string` | The fingerprint |
| `verify(string $expected): bool` | Constant-time check |
| `result(): FingerprintResult` | The fingerprint with its canonical bytes and normalized value |

### SignatureBuilder

| Method | Description |
| --- | --- |
| `fields(array $fields)` | The fields to sign, in order; string keys rename them |
| `valuesOnly()` / `keyValue()` | Joins values only (default) or key-value pairs |
| `separator(string $separator)` | Between fields (`''`) |
| `keyValueSeparator(string $separator)` | Between a key and its value (`'='`) |
| `onMissing(string\|Closure $strategy)` | `'throw'` (default), `'empty'`, `'null'`, `'skip'` or a closure |
| `onNull(string\|Closure $strategy)` | The same choices, for null values |
| `at()`, `transform()`, `enumsByName()`, `enumsByValue()`, `trim()`, `normalizeWhitespace()`, `lower()`, `upper()`, `unicode()`, `precision()` | Value rules per field, as on `FingerprintBuilder` |
| `algorithm()`, `hmac()`, `hex()`, `binary()`, `base64()`, `base64Url()` | Hashing options |
| `message(): string` | The exact string that is signed |
| `make(): string` | The signature |
| `verify(string $expected): bool` | Constant-time check |

### DigestBuilder

| Method | Description |
| --- | --- |
| `algorithm()`, `hmac()`, `hex()`, `binary()`, `base64()`, `base64Url()` | Hashing options |
| `make(): string` | The fingerprint of the bytes |
| `verify(string $expected): bool` | Constant-time check |

### FingerprintResult

| Method | Returns |
| --- | --- |
| `hash()` | `string`: the fingerprint, as `make()` returns it |
| `algorithm()` | `string` |
| `encoding()` | `string`: `hex`, `binary`, `base64` or `base64url` |
| `hmac()` | `bool`: whether it is an HMAC |
| `namespace()` | `?string` |
| `version()` | `string`: the canonical format version, `v1` |
| `canonical()` | `string`: the exact bytes that were hashed |
| `normalized()` | `mixed`: the value after filters and rules |
| `toArray()` | `array` of all of the above |

### Exceptions

Every exception implements `Pharaonic\Fingerprint\Exceptions\FingerprintException`, so one `catch` covers them all.

| Exception | Extends | Thrown when |
| --- | --- | --- |
| `ConfigurationException` | `InvalidArgumentException` | A path, algorithm, rule, strategy or callback result is invalid |
| `UnsupportedValueException` | `InvalidArgumentException` | A value can't be fingerprinted or signed |
| `CircularReferenceException` | `UnsupportedValueException` | An object contains itself |
| `MaximumDepthExceededException` | `UnsupportedValueException` | A value nests deeper than `maxDepth()` |
| `MissingFieldException` | `UnexpectedValueException` | A signature field is missing and `onMissing()` is `'throw'` |
| `MissingExtensionException` | `LogicException` | `unicode()` is used without ext-intl or the polyfill |
| `UnreadableResourceException` | `RuntimeException` | A file or stream can't be read |

Builders also throw a `LogicException` when you try to `serialize()` them.
