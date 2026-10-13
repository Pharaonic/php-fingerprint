## Installation

Install the package with Composer. There is nothing to register or configure.

### Requirements

- PHP 8.2
- `ext-mbstring`
- `ext-intl` or `symfony/polyfill-intl-normalizer`, only if you use the `unicode()` rule

There are no other runtime dependencies.

### Composer Installation

```bash title="Terminal" no-line-numbers
composer require pharaonic/php-fingerprint
```

Composer autoloads the `Pharaonic\Fingerprint` namespace.

:::success Installation Complete
You're all set! Try `Pharaonic\Fingerprint\Fingerprint::make(['id' => 7])`, which returns a 64-character SHA-256 hex string.
:::

### Upgrading from 8.0.x or 8.1.x

Require PHP 8.2 and update the package. Nothing else changes: no method was removed or changed, and every value the earlier versions accept keeps its fingerprint, so stored fingerprints still match.

- From 8.0.x, you also get enum support ([Enums](#normalization)) and the `xxh*` and `murmur3*` algorithms ([Hashing & HMAC](#hashing)).
- 8.2.x marks the HMAC secret as a sensitive parameter, so PHP hides it in every stack trace ([Keeping Secrets Safe](#hashing)).
