# Changelog

All notable changes to this project are documented in this file.

## 8.0.0 - Unreleased

### Added

- `Fingerprint::make()` and `Fingerprint::for()`: SHA-256 fingerprints of `null`, booleans, integers, floats, strings and arrays, encoded with canonical format v1.
- Associative keys are ordered canonically at every level, lists keep their order, and types are preserved (`1`, `'1'`, `1.0` and `true` all differ).
- Closures, resources, objects, `NAN` and values nested deeper than 512 levels throw an `UnsupportedValueException` that names the offending path.
- Filters: `ignore()`, `only()` (path order never matters), `ignoreWhen()`, `ignoreNulls()`, `ignoreEmptyStrings()` and `ignoreEmptyArrays()`. Lists stay lists and are re-indexed after removals; maps stay maps even when emptied.
- Path rules: `at()` scopes the following value rules to a dot path, and `transform()` replaces a value before it is fingerprinted. Paths use php-dot-array's syntax: `*` matches one key or index, and `\.`, `\*` and `\\` escape a literal character.
- `FingerprintBuilder::for()` reuses a configuration for another value. Invalid paths, path lists and callback results throw a `ConfigurationException`.
