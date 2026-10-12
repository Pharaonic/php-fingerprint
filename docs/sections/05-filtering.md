## Filtering

You already know `ignore()` from the [Quick Start](#quick-start). This page adds selecting fields, dropping empty values, and ignoring by condition.

Filters decide which values take part in the fingerprint.

### ignore()

Leave out the values at one key or several:

```php
Fingerprint::for($order)->ignore('updated_at')->make();

Fingerprint::for($order)->ignore(['updated_at', 'views'])->make();
```

Keys can point inside nested arrays too, such as `customer.updated_at`. The [next page](#paths) covers that.

### only()

The opposite of `ignore()`: keep only the values at these keys, and everything under them:

```php
Fingerprint::for(['id' => 7, 'name' => 'Ahmed', 'views' => 120])
    ->only(['id', 'name'])
    ->result()->normalized();
// ['id' => 7, 'name' => 'Ahmed']
```

The order of the keys never matters, and calling `only()` again adds more keys. When nothing matches at all, an array becomes an empty map. `ignore()` wins over `only()` when both match.

### ignoreNulls(), ignoreEmptyStrings(), ignoreEmptyArrays()

Leave out values that end up `null`, `''` or an empty array. They check values from the deepest up, so an array emptied by other filters is removed too:

```php
Fingerprint::for(['a' => ['b' => null], 'c' => ''])
    ->ignoreNulls()
    ->ignoreEmptyArrays()
    ->result()->normalized();
// ['c' => '']
```

They are off by default because `null` and a missing key are different data.

### ignoreWhen()

When no fixed list of keys fits, leave out every value for which a callback returns `true`. The callback receives the value's dot path and the value itself, and must return a boolean:

```php
Fingerprint::for($payload)
    ->ignoreWhen(fn (string $path, $value) => str_starts_with($path, '_'))
    ->make();
```

The callback runs for every value under the root, so keep it cheap. A key with a dot in its name is passed escaped: `a\.b`.

:::info Filters and lists
A list that loses elements stays a list and is re-indexed. A map that loses every key stays an empty map, which differs from `[]`.
:::

**Next:** [Paths & Wildcards](#paths): reach nested values, and apply rules to one field only.
