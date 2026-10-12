:::badges
- PHP Package {color=blue}
- {release.label} {color=green}
- {package.license} License {color=purple}
:::

# Fingerprint

Deterministic fingerprints and ordered signatures for PHP data. Fingerprint answers two different questions, and keeps them apart:

- **Fingerprints** (`Fingerprint::make()`, `Fingerprint::for()`): *are these two PHP values the same data?* Associative key order never matters, list order and types do, and you declare what else to ignore or normalize. Use them for change detection, de-duplication, cache and idempotency keys, and sync.
- **Signatures** (`Fingerprint::signature()`): *does this payload carry this protocol's signature?* The fields are joined in exactly the order you declare, then hashed or signed with HMAC, as payment gateways and webhooks expect. Nothing is sorted.

The same data with the same configuration gives the same fingerprint on every machine, locale, `precision` ini setting and supported PHP version.

:::features
### Key Order Never Matters {icon="switch"}
Associative arrays and stdClass objects are ordered canonically at every level, so a reordered payload keeps its fingerprint.

### Types Are Preserved {icon="badge-check"}
1, "1", 1.0 and true give four different fingerprints, and null is not the same as a missing key.

### Path Rules {icon="code-brackets"}
Ignore, select, trim, lowercase, round, sort or transform values at dot paths such as items.*.price.

### Hashing and HMAC {icon="lock"}
SHA-256 by default, any algorithm PHP supports, HMAC secrets, hex, binary, base64 or URL-safe base64 output.

### Ordered Signatures {icon="shield-check"}
Build the exact message a payment gateway or webhook signs, field by field, and verify it in constant time.

### Files and Streams {icon="document"}
Fingerprint large files and streams in chunks, without loading them into memory.
:::

:::info Quick Tip
Not sure why two values give different fingerprints? Compare `Fingerprint::for($a)->result()->canonical()` with the same call for `$b`: it shows the exact bytes that were hashed.
:::
