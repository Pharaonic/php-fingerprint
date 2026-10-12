# Changelog

All notable changes to this project are documented in this file.

## 8.0.0 - Unreleased

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
- `FingerprintBuilder::for()` reuses a configuration for another value. Invalid paths, path lists and callback results throw a `ConfigurationException`.
