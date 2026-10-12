## Filtering

Filters decide which values take part in the fingerprint. They are global: `at()` does not scope them.

### ignore()

Leave out the values at one path or several. Wildcards remove every match:

```php
Fingerprint::for($order)->ignore('updated_at')->make();

Fingerprint::for($order)->ignore(['updated_at', 'items.*.cached_label'])->make();
```

### only()

Keep only the values at these paths, everything under them, and the containers leading to them. The order of the paths never matters, and calling `only()` again adds more paths:

```php
Fingerprint::for([
    'user' => ['email' => 'a@example.com', 'name' => 'Ahmed'],
    'meta' => ['seen' => 3],
])->only(['user.email'])->result()->normalized();
// ['user' => ['email' => 'a@example.com']]
```

A container that ends up with no selected value is left out. When nothing matches at all, an array root becomes an empty map. `ignore()` wins over `only()` when both match.

### ignoreWhen()

Leave out every value for which a callback returns `true`. The callback receives the value's escaped dot path and the value before any value rule applies. It must return a boolean:

```php
Fingerprint::for($payload)
    ->ignoreWhen(fn (string $path, $value) => str_starts_with($path, '_'))
    ->make();
```

The callback runs for every value under the root, so keep it cheap. A key with a dot in its name is passed escaped: `a\.b`.

### ignoreNulls(), ignoreEmptyStrings(), ignoreEmptyArrays()

These check each value after its rules apply, from the deepest values up. An array emptied by other filters is removed too:

```php
Fingerprint::for(['a' => ['b' => null], 'c' => ''])
    ->ignoreNulls()
    ->ignoreEmptyArrays()
    ->result()->normalized();
// ['c' => '']
```

They are off by default because `null` and a missing key are different data.

:::info Filters and lists
A list that loses elements stays a list and is re-indexed, while paths keep referring to the input indexes. A map that loses every key stays an empty map, which differs from `[]`.
:::
