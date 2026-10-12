# Maintaining This Package

This file records package-specific maintainer facts that are safe to keep in the public repository.

## Purpose

Deterministic fingerprints of structured PHP data (canonical mode) and ordered protocol signatures (signature mode). Audit logs, diffs, persistence, HTTP transport, encryption and framework behavior belong to other packages; Laravel integration belongs to a future `laravel-fingerprint`.

## Supported PHP lines

One branch per PHP minor, each requiring exactly that minor (`"php": ">=8.0 <8.1"`):

```text
8.0.x  8.1.x  8.2.x  8.3.x  8.4.x  8.5.x
```

Enum support starts on `8.1.x`. `8.0.x` has none in any form.

## Public API invariants

- Canonical mode and signature mode are separate pipelines and must stay separate.
- Associative key order never affects a canonical fingerprint, lists keep their order by default, types are preserved, and `null` differs from a missing key.
- **Canonical format v1 is shared by every line.** `tests/Fixtures/vectors.php` holds hand-written golden vectors; every line must reproduce them byte for byte. Changing an existing vector is a canonical-format change and needs a new format version. Lines may only add vectors.
- The canonical form never depends on ini settings, the locale or the PHP version: floats are encoded from their IEEE-754 bits, map keys are ordered with `strcmp()`, and `round()` (which changed in PHP 8.4) is never used.
- Every exception implements `Exceptions\FingerprintException`. Everything under `Support\` is `@internal`.

## Package-specific constraints

- Only `ext-mbstring` is required at runtime; `ext-intl` stays a `suggest` for Unicode normalization.
- CI runs the tests on Linux and Windows, with lowest and highest dependencies, and once without `ext-intl`.

## Branch notes

Tooling follows the line's PHP: Pest 1 on `8.0.x` and `8.1.x`, Pest 3 on `8.2.x`, Pest 4 from `8.3.x`; Pint uses the `psr12` preset on `8.0.x` and `per` on the other lines.

## Documentation

https://pharaonic.dev/packages/php/fingerprint

Keep this file factual and package-specific. General Pharaonic engineering policy belongs in the private engineering repository, not here.
