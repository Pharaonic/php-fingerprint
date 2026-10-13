## Hashing & HMAC

The default (SHA-256, hex) fits most uses. Read this when the fingerprint must be secret, shorter, or in another format. The options go from the simplest (another algorithm or format) to the one that needs care (a secret).

Every builder (`FingerprintBuilder`, `SignatureBuilder` and `DigestBuilder`) shares the same hashing options.

### Algorithms

SHA-256 is the default. `algorithm()` accepts any algorithm your PHP's `hash()` supports:

```php
Fingerprint::for($data)->algorithm('sha512')->make();
Fingerprint::for($data)->algorithm('crc32b')->make(); // "a543c5c7" for ['a' => 1]
```

The fast `xxh32`, `xxh64`, `xxh3`, `xxh128`, `murmur3a`, `murmur3c` and `murmur3f` algorithms are available too:

```php
Fingerprint::for(['a' => 1])->algorithm('xxh128')->make(); // "9f013320af9f365ab723e99883d27b08"
```

An algorithm PHP doesn't have throws a `ConfigurationException` instead of falling back:

```php
Fingerprint::for($data)->algorithm('sha257');
// ConfigurationException: The hash algorithm "sha257" is not available on PHP 8.4.19.
```

:::warning Non-cryptographic algorithms
`xxh*`, `murmur3*`, `crc32b`, `fnv1a64`, `md5` and similar algorithms are fine for change detection and cache keys, but anyone can produce a collision. Use SHA-256 or stronger, with `hmac()` (below), when the fingerprint protects anything. `xxh*`, `murmur3*` and the checksums can't be used with `hmac()` at all.
:::

### Output Encodings

| Method | Output for `['a' => 1]` |
| --- | --- |
| `hex()` (default) | `8007ef36e8ee76e080e880d450b79d39cd4a544ae3962f08f40d96211ae3946e` |
| `binary()` | the 32 raw bytes |
| `base64()` | `gAfvNujuduCA6IDUULedOc1KVErjli8I9A2WIRrjlG4=` |
| `base64Url()` | `gAfvNujuduCA6IDUULedOc1KVErjli8I9A2WIRrjlG4` (`-` and `_`, no padding) |

### Namespaces

`namespace()` keeps fingerprints of the same data used for different purposes apart. An `"invoice"` fingerprint never matches an `"order"` fingerprint of the same data, nor one without a namespace:

```php
$invoice = Fingerprint::for(['id' => 7])->namespace('invoice')->make();
$order = Fingerprint::for(['id' => 7])->namespace('order')->make();

$invoice === $order; // false
```

A namespace is not a secret: anyone who knows it can compute the same fingerprint. That's what HMAC is for.

### HMAC

`hmac()` signs with a secret instead of computing a plain hash, so nobody without the secret can produce a matching fingerprint:

```php
Fingerprint::for($data)->hmac($secret)->make();
Fingerprint::for($data)->algorithm('sha512')->hmac($secret)->make();
```

An empty secret throws a `ConfigurationException`. An algorithm that cannot do HMAC, such as `crc32b`, `fnv1a64` or `joaat`, throws a `ConfigurationException` when you hash, not when you call `hmac()`.

### Verifying

`verify()` recomputes the fingerprint with the same algorithm, secret, namespace and encoding, and compares it with `hash_equals()` in constant time. Hex is compared case-insensitively, other encodings exactly:

```php
$builder = Fingerprint::for($data)->namespace('webhook')->hmac($secret);

$builder->verify($received); // true or false
```

`Fingerprint::verify($value, $expected)` is the shortcut for a plain SHA-256 hex fingerprint.

### Keeping Secrets Safe

- `var_dump()` and `print_r()` of a builder show only the algorithm, encoding, whether HMAC is on, and the namespace (or the signature fields).
- `serialize()` of a builder throws a `LogicException`, so a builder never ends up in a cache or a queue with its secret.
- No exception message or trace from the package contains the secret, and `result()` never holds it. `hmac()` marks the secret with `#[\SensitiveParameter]`, so even a trace with arguments shows a `SensitiveParameterValue` instead of it.
- PHP itself can still read private properties through `var_export()`, `(array)` casts and Reflection, so don't pass builders to code that dumps them that way.

### What a Fingerprint Is Not

- A fingerprint is **not encryption**: it can't be reversed, but anyone can fingerprint guessed data and compare. Use `hmac()` when the data is guessable and the fingerprint must not be forgeable.
- A **namespace is not a secret**: it only keeps fingerprints for different purposes apart.
- There is **no salt** option. Use `namespace()` to separate purposes and `hmac()` for secrecy.

**Next:** [Files & Streams](#files-and-streams): the same hashing options for raw bytes.
