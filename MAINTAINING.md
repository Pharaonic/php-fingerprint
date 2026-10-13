# Maintaining This Package

This file records package-specific maintainer facts that are safe to keep in the public repository.

## Purpose

Deterministic fingerprints of structured PHP data (canonical mode) and ordered protocol signatures (signature mode). Audit logs, diffs, persistence, HTTP transport, encryption and framework behavior belong to other packages; Laravel integration belongs to a future `laravel-fingerprint`.

## Supported PHP lines

One branch per PHP minor, each requiring exactly that minor (`"php": ">=8.3 <8.4"`):

```text
8.0.x  8.1.x  8.2.x  8.3.x  8.4.x  8.5.x
```

## Public API invariants

- Canonical mode and signature mode are separate pipelines and must stay separate.
- Associative key order never affects a canonical fingerprint, lists keep their order by default, types are preserved, and `null` differs from a missing key.
- **Canonical format v1 is shared by every line.** `tests/Fixtures/vectors.php` holds hand-written golden vectors; every line must reproduce them byte for byte. Changing an existing vector is a canonical-format change and needs a new format version. Lines may only add vectors.
- Enum cases are encoded as `e` + class + case name + backed value (`n` when pure); their vectors start on `8.1.x`. `enumsByName()`/`enumsByValue()` are never the default, and signatures sign only backed values unless a rule says otherwise.
- The canonical form never depends on ini settings, the locale or the PHP version: floats are encoded from their IEEE-754 bits, map keys are ordered with `strcmp()`, and `round()` (which changed in PHP 8.4) is never used.
- Every exception implements `Exceptions\FingerprintException`. Everything under `Support\` is `@internal`.

## Package-specific constraints

- Only `ext-mbstring` is required at runtime. `unicode()` needs a `Normalizer` class: ext-intl or `symfony/polyfill-intl-normalizer`. The dev tools pull in the polyfill, so the missing-extension test runs in a child PHP process that autoloads only `src/`, on PHP builds without intl (CI's no-intl job).
- `lower()`, `upper()` and `unicode()` depend on the Unicode tables of mbstring and ICU, which can differ between PHP builds for newly assigned code points. Golden vectors only use characters that are stable across them.
- CI runs the tests on Linux and Windows, with lowest and highest dependencies, and once without `ext-intl`.

## Security notes

- Every parameter that receives the HMAC secret (`hmac()`, `Digest::withSecret()`) has `#[SensitiveParameter]`, and `SecretsTest` checks it. The lines before 8.2 cannot use the attribute, so they never throw while a frame holds the secret: `hmac()` only rejects an empty secret, and the HMAC-algorithm check runs when hashing. The lines from 8.2 keep that order so the behavior stays the same on every line. `SecretsTest` checks the package's frames with `zend.exception_ignore_args` off.
- The secret is hidden from `var_dump()`/`print_r()` through `__debugInfo()` and builders refuse `serialize()`, but `var_export()`, `(array)` casts and Reflection can still read private properties; that cannot be prevented in PHP.
- `Fingerprint::file()` reads any path PHP's stat functions accept, stream wrappers included: paths must come from trusted code. PHP 8.0 no longer unserializes Phar metadata on file access.
- Callbacks (`transform()`, `normalize()`, `sortUsing()`, `ignoreWhen()`, signature strategies) and user objects (`JsonSerializable`, `Traversable`, `ArrayAccess`) run application code and are trusted.

## Branch notes

Tooling follows the line's PHP: Pest 1 on `8.0.x` and `8.1.x`, Pest 3 on `8.2.x`, Pest 4 from `8.3.x`; Pint uses the `psr12` preset on `8.0.x` and `per` on the other lines.

## Documentation

https://pharaonic.dev/packages/php/fingerprint

Keep this file factual and package-specific. General Pharaonic engineering policy belongs in the private engineering repository, not here.
