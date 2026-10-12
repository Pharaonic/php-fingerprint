## Troubleshooting

### Two values I consider equal get different fingerprints

Compare their canonical bytes to find the difference:

```php
Fingerprint::for($a)->result()->canonical();
Fingerprint::for($b)->result()->canonical();
```

The usual causes are a type difference (`7` and `'7'`, `1` and `1.0`), a list in a different order, a `null` against a missing key, or a float such as `0.1 + 0.2`. Use `normalizeNumericStrings()`, `unordered()`, `ignoreNulls()` or `precision()` accordingly.

### The fingerprint changes when I reload the same record

A field changes on every load: a timestamp, a counter, a cache value. Leave it out with `ignore()`, or select what matters with `only()`.

### A rule doesn't apply where I expect

- Rules declared after `at()` only apply at that path, until the next `at()`. Declare global rules first.
- For the same kind of rule, the last one wins for a path: a later `upper()` replaces an earlier `lower()`.
- Paths refer to input keys: a key with a dot in its name needs `\.`, as in `config.app\.name`.

### UnsupportedValueException for an object

Only `stdClass`, dates, `JsonSerializable` and `Traversable` objects are converted automatically. Add a `normalize()` callback for the class, or turn the object into an array first. `Stringable` objects need `normalize(Stringable::class, fn ($value) => (string) $value)`.

### UnsupportedValueException: invalid UTF-8

`lower()`, `upper()`, `trim()`, `normalizeWhitespace()` and `unicode()` need valid UTF-8. Scope them with `at()` so they don't reach binary fields, or convert the encoding first.

### MaximumDepthExceededException

The value nests deeper than 512 levels, or an array references itself. Fix the reference, or raise the limit with `maxDepth()` if the data really is that deep.

### ConfigurationException: algorithm not available

Your PHP build doesn't have that algorithm. PHP 8.0 has no `xxh*` or `murmur3*`. List what's available with `hash_algos()`.

### ConfigurationException: cannot be used for HMAC

Some algorithms, such as `crc32b`, `fnv1a64` and `joaat`, can't do HMAC. This is reported when you call `make()` or `verify()`, not `hmac()`. Use `sha256` or stronger.

### A signature doesn't match the provider's

Print the message and compare it with the provider's documentation:

```php
Fingerprint::signature($payload)->fields($fields)->message();
```

Check the field order, the separators, how booleans and amounts are written (`true` or `1`, `1050` or `10.50`), and whether missing fields should be empty or skipped. Signatures never sort fields: the order of `fields()` is the order of the message.

### A float field throws in a signature

Floats need `precision()` on their field, so the message never depends on PHP's `precision` ini setting: `->at('total')->precision(2)`.

### A stream fingerprint is the hash of an empty string

The stream was already at its end. Streams are read from their current position and never rewound: call `rewind($stream)` first.

### MissingExtensionException for unicode()

Install `ext-intl`, or `composer require symfony/polyfill-intl-normalizer`.
