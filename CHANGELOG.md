# Changelog

All notable changes to this project are documented in this file.

## 8.0.3 - 2026-10-13

### Changed

- The docs describe only the 8.0.x line: the Installation and Guarantees & Limitations pages no longer mention features or release lines of later PHP versions.
- The Overview drops the Learning Path table, and the Quick Start links straight to Ordered Signatures and Signature Examples.

## 8.0.2 - 2026-10-12

### Changed

- The documentation reads as a learning path, from simple to advanced: Getting Started, The Basics, Going Further, Signed Callbacks & Webhooks, then Reference. Each page builds only on the pages before it and links to the next one.
- A new Signature Examples page covers your own webhooks, Paymob callbacks and Kashier payment hashes, and a new How Rules Combine page explains rule order and precedence.
- More examples clarify `sortBy()`, namespaces, `onMissing()`, `JsonSerializable` objects and input paths, and the README labels its documentation link.

## 8.0.1 - 2026-10-12

### Changed

- The package page and README show the package cover, and the README links to Packagist with version and download badges.

## 8.0.0 - 2026-10-12

### Added

- `Fingerprint::make()` and `Fingerprint::for()`: SHA-256 fingerprints of `null`, booleans, integers, floats, strings and arrays, encoded with canonical format v1.
- Associative keys are ordered canonically at every level, lists keep their order, and types are preserved (`1`, `'1'`, `1.0` and `true` all differ).
- Closures, resources, unsupported objects, `NAN` and values nested deeper than 512 levels throw an `UnsupportedValueException` that names the offending path.
- Filters: `ignore()`, `only()` (path order never matters), `ignoreWhen()`, `ignoreNulls()`, `ignoreEmptyStrings()` and `ignoreEmptyArrays()`. Lists stay lists and are re-indexed after removals; maps stay maps even when emptied.
- Path rules: `at()` scopes the following value rules to a dot path, and `transform()` replaces a value before it is fingerprinted. Paths use php-dot-array's syntax: `*` matches one key or index, and `\.`, `\*` and `\\` escape a literal character.
- List rules: `unordered()`, `sortBy()` (one or more fields or dot paths, ascending or descending), `sortUsing()`, `unique()` and `uniqueBy()`. Lists are ordered first, then de-duplicated keeping the first occurrence; the rules leave maps and scalars alone.
- String rules: `trim()` and `normalizeWhitespace()` (ASCII whitespace), `lower()` and `upper()` (UTF-8 through mbstring), and `unicode()` (NFC, NFD, NFKC, NFKD; needs ext-intl or symfony/polyfill-intl-normalizer). Invalid UTF-8 is rejected instead of silently replaced.
- Number and boolean rules: `precision()` rounds floats from their exact binary value, the same on every PHP version; `normalizeNumericStrings()` converts plain decimal strings; `normalizeBooleans()` maps `true`, `1`, `'1'`, `'true'` and their false counterparts.
- Dates: `DateTimeInterface` values are fingerprinted by their UTC instant with microseconds; `datePrecision()` truncates them and `keepTimezone()` keeps the local time and timezone instead.
- Objects: `stdClass` (always a map), `JsonSerializable`, `Traversable` (generators excluded) and `normalize(Class::class, $callback)` for any class or interface. Other objects are rejected, private state is never read, and objects that contain themselves throw a `CircularReferenceException`.
- Hashing: `algorithm()` accepts any algorithm `hash()` supports (SHA-256 by default), `hmac()` signs with a secret, and `hex()`, `binary()`, `base64()` and `base64Url()` choose the output. Unavailable algorithms and non-HMAC algorithms with a secret throw instead of falling back.
- `namespace()` keeps fingerprints of identical data used for different purposes apart.
- `Fingerprint::verify()` and `FingerprintBuilder::verify()` compare in constant time, hex case-insensitively.
- The HMAC secret never appears in `var_dump()`, `print_r()`, exception messages or exception traces, and builders refuse to be serialized. An algorithm that cannot do HMAC is therefore reported when hashing, not by `hmac()` itself.
- Ordered signatures: `Fingerprint::signature($payload)->fields([...])` joins the selected fields in exactly the declared order and hashes or signs them, for protocols such as payment gateway callbacks. `valuesOnly()` or `keyValue()`, `separator()` and `keyValueSeparator()` shape the message, `onMissing()` and `onNull()` decide what missing and null fields become (both throw by default), and `at()` applies string rules, `precision()` and `transform()` per field. `message()` shows the exact string that is signed, and `verify()` checks a received signature in constant time.
- `Fingerprint::file()` and `Fingerprint::stream()` fingerprint raw bytes in chunks, never loading a whole file into memory, with the same algorithm, HMAC, encoding and `verify()` options. Streams are read from their current position and are never rewound or closed. Missing files, directories and non-readable streams throw an `UnreadableResourceException`.
- `FingerprintBuilder::result()` returns a `FingerprintResult` with the hash, algorithm, encoding, HMAC flag, namespace, format version, the exact canonical bytes and the normalized value as plain PHP data, to see why two fingerprints differ. It never holds the secret.
- `maxDepth()` changes the nesting limit (512 by default, like `json_decode()`).
- `FingerprintBuilder::for()` reuses a configuration for another value. Invalid paths, path lists and callback results throw a `ConfigurationException`.
