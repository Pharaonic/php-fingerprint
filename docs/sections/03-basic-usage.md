## Basic Usage

Pass any supported value to `Fingerprint::make()` to get its SHA-256 fingerprint as lowercase hex:

```php
use Pharaonic\Fingerprint\Fingerprint;

Fingerprint::make(['id' => 7, 'tags' => ['php', 'laravel']]);
// "ef01cc9f8e23770fff6d1a698f7bd35b3ac2e2509434f787f8b872a32cfd7f25"
```

### What Counts as the Same Data

```php
$a = Fingerprint::make(['id' => 7, 'tags' => ['php', 'laravel']]);

$a === Fingerprint::make(['tags' => ['php', 'laravel'], 'id' => 7]); // true: key order never matters
$a === Fingerprint::make(['id' => '7', 'tags' => ['php', 'laravel']]); // false: 7 and "7" differ
$a === Fingerprint::make(['id' => 7, 'tags' => ['laravel', 'php']]); // false: list order matters
```

`null` and a missing key are different too: `['a' => null]` and `[]` never share a fingerprint. [Canonical Rules](#canonical-rules) explains every rule.

### Verifying a Fingerprint

`Fingerprint::verify()` compares a value with a stored SHA-256 hex fingerprint in constant time. Hex is compared case-insensitively:

```php
Fingerprint::verify($payload, $storedFingerprint); // true or false
```

### Configuring a Fingerprint

`Fingerprint::for()` returns a `FingerprintBuilder`. Chain rules, then call `make()`, `verify()` or `result()`:

```php
$fingerprint = Fingerprint::for($order)
    ->ignore(['updated_at', 'items.*.cached_label'])
    ->at('customer.email')->trim()->lower()
    ->at('items')->sortBy('sku')
    ->make();
```

Value rules such as `trim()` apply to every value until `at()` is called, then to the values at the latest `at()` path. See [Paths & Wildcards](#paths).

### Reusing a Configuration

Builders are immutable: every method returns a new builder and leaves the current one unchanged. Configure once, then bind other values with `for()`:

```php
$orders = Fingerprint::for(null)
    ->namespace('order')
    ->ignore('updated_at')
    ->at('items')->unordered();

$first = $orders->for($orderA)->make();
$second = $orders->for($orderB)->make();
```

:::tip Two modes, never mixed
Use `Fingerprint::make()`/`for()` to compare data, and `Fingerprint::signature()` only to reproduce a protocol's ordered signature. See [Ordered Signatures](#signatures).
:::
