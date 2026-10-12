## Quick Start

You only need three methods to start. Everything else in these docs is optional.

### 1. Make a Fingerprint

`Fingerprint::make()` turns an array or a value into a short string. The same data always gives the same string:

```php
use Pharaonic\Fingerprint\Fingerprint;

$fingerprint = Fingerprint::make(['id' => 7, 'name' => 'Ahmed']);
// "2f6edddef0f7abe5b4300047c00b0622fcb4c2822e6c00e5d624015751bedf6d"
```

The order of keys doesn't matter, so these two give the same fingerprint:

```php
Fingerprint::make(['id' => 7, 'name' => 'Ahmed']) === Fingerprint::make(['name' => 'Ahmed', 'id' => 7]); // true
```

### 2. Check If Data Changed

Store the fingerprint, then later check whether the data is still the same:

```php
if (Fingerprint::verify($product, $storedFingerprint)) {
    // nothing changed
}
```

### 3. Ignore What Doesn't Matter

Some fields change all the time, such as `updated_at`. Leave them out with `for()` and `ignore()`, then call `make()`:

```php
$fingerprint = Fingerprint::for($product)
    ->ignore(['updated_at', 'views'])
    ->make();
```

That's it. You now know enough for most uses.

### Simple Recipes

**Only sync records that changed:**

```php
$new = Fingerprint::for($product)->ignore('updated_at')->make();

if ($new !== $product['fingerprint']) {
    // push the product, then save $new as its fingerprint
}
```

**Find duplicate rows in an import:**

```php
$seen = [];

foreach ($rows as $row) {
    $key = Fingerprint::make($row);

    if (isset($seen[$key])) {
        continue; // a duplicate
    }

    $seen[$key] = true;
}
```

**Verify a payment gateway callback**, such as Paymob: see [Ordered Signatures](#signatures), which has a complete step-by-step example.

### Good to Know

- `7` and `'7'` are different, and so are `[1, 2]` and `[2, 1]`. Read [Canonical Rules](#canonical-rules) when two values you expect to match don't.
- Builders never change: each method returns a new one, so you can keep a configured builder and reuse it with `->for($otherData)`.
- When you need more, such as trimming strings, rounding prices or ignoring list order, the **Advanced** pages cover it.
