## Guarantees & Limitations

### Determinism

The same value with the same configuration gives the same fingerprint:

- on every run, machine and operating system;
- whatever the locale (`setlocale()`) and the `precision` / `serialize_precision` ini settings;
- on every supported PHP version and release line: canonical format v1 is the same on all of them, and the test suite checks hand-written golden vectors on each line.

The canonical form starts with a version header (`fp1;`). If the format ever has to change, it gets a new version, so old and new fingerprints can never collide by accident.

### Invariants

- Associative key order never affects a fingerprint, at any depth. This can't be turned off.
- Lists keep their order unless you choose a list rule.
- Types are preserved unless you choose a normalization rule.
- `null` differs from a missing key unless you use `ignoreNulls()`.
- The order of `only()` paths never matters. The order of signature `fields()` always does.

### Release Lines

Each release line requires exactly one PHP version and keeps canonical format v1:

| Line | PHP |
| --- | --- |
| 8.0.x | 8.0 |

### Limitations

- **Hash algorithms:** only the algorithms of your PHP build are available. PHP 8.0 has no `xxh*` or `murmur3*`.
- **Unicode tables:** `lower()`, `upper()` and `unicode()` use the Unicode tables of mbstring and ICU. Characters added in recent Unicode versions can be handled differently by older PHP or ICU builds, so avoid relying on them when fingerprints must match across servers.
- **Named timezones:** dates built from named zones depend on each PHP build's timezone database. See [Dates](#normalization).
- **Numeric string keys:** PHP casts the key `'1'` to `1` before the package sees it, so the two can't be told apart.
- **Arbitrary objects** are rejected instead of read through their public properties. Convert them with `normalize()`.
- **Cross-language use:** the canonical form is PHP-specific (it keeps `1` and `1.0` apart, unlike JSON). Use signatures when another system must compute the same value.
