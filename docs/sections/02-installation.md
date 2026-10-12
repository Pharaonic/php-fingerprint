## Installation

Install the package with Composer. There is nothing to register or configure.

### Requirements

- PHP 8.0.x (each `8.x` release line targets the matching PHP version)
- `ext-mbstring`
- `ext-intl` or `symfony/polyfill-intl-normalizer`, only if you use the `unicode()` rule

There are no other runtime dependencies.

### Composer Installation

```bash title="Terminal" no-line-numbers
composer require pharaonic/php-fingerprint
```

Composer autoloads the `Pharaonic\Fingerprint` namespace.

:::info Enums
The 8.0.x line runs on PHP 8.0, which has no enums, so it has no enum support of any kind.
:::

:::success Installation Complete
You're all set! Try `Pharaonic\Fingerprint\Fingerprint::make(['id' => 7])`, which returns a 64-character SHA-256 hex string.
:::
