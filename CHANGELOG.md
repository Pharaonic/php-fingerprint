# Changelog

All notable changes to this project are documented in this file.

## 8.0.0 - Unreleased

### Added

- `Fingerprint::make()` and `Fingerprint::for()`: SHA-256 fingerprints of `null`, booleans, integers, floats, strings and arrays, encoded with canonical format v1.
- Associative keys are ordered canonically at every level, lists keep their order, and types are preserved (`1`, `'1'`, `1.0` and `true` all differ).
- Closures, resources, objects, `NAN` and values nested deeper than 512 levels throw an `UnsupportedValueException` that names the offending path.
