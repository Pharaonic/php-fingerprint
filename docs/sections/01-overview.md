:::badges
- PHP Package {color=blue}
- {release.label} {color=green}
- {package.license} License {color=purple}
:::

# Fingerprint

Fingerprint turns PHP data into a short, stable string. If the data is the same, the string is the same, even when array keys are in a different order. Use it to detect changes, find duplicates, or build cache and idempotency keys:

```php
use Pharaonic\Fingerprint\Fingerprint;

$fingerprint = Fingerprint::make($product);         // store it
Fingerprint::verify($product, $fingerprint);        // true while nothing changed
```

It also verifies **payment gateway and webhook signatures**, such as Paymob callbacks, with `Fingerprint::signature()`. See [Ordered Signatures](#signatures).

New here? Start with the [Quick Start](#quick-start): three methods cover most uses.

### Learning Path

The docs are written to be read in order. Each step only uses what the steps before it explained, and you can stop at any step once it covers what you need:

| Step | Pages | You'll be able to |
| --- | --- | --- |
| 1. Getting Started | [Installation](#installation), [Quick Start](#quick-start) | Make a fingerprint, check whether data changed, ignore a field |
| 2. The Basics | [How Matching Works](#canonical-rules), [Filtering](#filtering), [Paths & Wildcards](#paths), [Normalization](#normalization), [Lists](#lists) | Decide exactly which values count and when two values are equal |
| 3. Going Further | [Hashing & HMAC](#hashing), [Files & Streams](#files-and-streams), [Objects](#objects), [How Rules Combine](#rule-order) | Use secrets and other algorithms, hash files, fingerprint your own classes |
| 4. Payment Gateways & Webhooks | [Ordered Signatures](#signatures) | Verify a Paymob callback or any signed webhook |

The **Reference** pages ([Debugging](#debugging), [Classes & Methods](#api-reference), [Examples](#examples), [Troubleshooting](#troubleshooting)…) are for looking things up, not for reading in order.

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
Some fields change all the time, like `updated_at`. Leave them out with `Fingerprint::for($data)->ignore('updated_at')->make()`.
:::
