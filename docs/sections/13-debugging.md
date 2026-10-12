## Debugging

`result()` returns a `FingerprintResult` with the fingerprint and everything needed to understand it:

```php
$result = Fingerprint::for(['b' => 1, 'a' => [true, null, 'x', 1.5]])->result();

$result->hash();       // the same string make() returns
$result->algorithm();  // "sha256"
$result->encoding();   // "hex"
$result->hmac();       // false
$result->namespace();  // null
$result->version();    // "v1"
$result->canonical();  // "fp1;nm2{s1:al4[b1ns1:xd3ff8000000000000]s1:bi1;}"
$result->normalized(); // ['b' => 1, 'a' => [true, null, 'x', 1.5]]
$result->toArray();    // all of the above, as an array
```

- `canonical()` holds the exact bytes that were hashed. Compare it for two values to see why their fingerprints differ.
- `normalized()` is the value after filters and value rules, as plain PHP data. Map keys keep their input order there; `canonical()` shows the canonical order. Dates appear as ISO 8601 strings: `"2026-10-12T07:00:00.000000Z"`, or the local time and timezone name with `keepTimezone()`.

A result never holds the HMAC secret, so it is safe to log.

### Reading the Canonical Bytes

| Token | Meaning |
| --- | --- |
| `fp1;` | Format v1 header |
| `n` / `s7:invoice` | No namespace / the namespace `invoice` |
| `n` | `null` |
| `b0` / `b1` | `false` / `true` |
| `i42;` | the integer 42 |
| `d3ff8000000000000` | a float, as its 8 IEEE-754 bytes in hexadecimal (here 1.5) |
| `s5:hello` | a string, with its length in bytes |
| `l2[…]` | a list of 2 elements |
| `m2{…}` | a map of 2 entries, keys in canonical order |
| `t2026-10-12T07:00:00.000000Z` | a date, as its UTC instant |
| `z39:2026-10-12T10:00:00.000000 Africa/Cairo` | a date with `keepTimezone()` |

For a signature, `message()` plays the same role: it returns the exact string that is hashed or signed.
