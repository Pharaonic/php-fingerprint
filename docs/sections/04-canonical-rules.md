## Canonical Rules

Read this when two values you expect to match get different fingerprints, or the other way round.

Before hashing, every value is written in a canonical form (format v1). These rules decide when two values share a fingerprint.

### Maps and Lists

- An array whose keys are exactly `0, 1, 2, …` in that order is a **list**. Its order matters.
- Any other array is a **map** (associative array). Its keys are ordered canonically at every level, so key order never matters.
- `[]` is an empty list.
- A list stays a list after filters remove elements, and is re-indexed. A map stays a map, even when filters empty it, so it doesn't match `[]`.
- A sparse array such as `[1 => 'a', 0 => 'b']` is a map.

```php
Fingerprint::make(['b' => 1, 'a' => 2]) === Fingerprint::make(['a' => 2, 'b' => 1]); // true
Fingerprint::make([1, 2]) === Fingerprint::make([2, 1]);                             // false
```

Use [`unordered()`](#lists) when a list's order doesn't matter to you.

### Types

Types are preserved: `1`, `'1'`, `1.0` and `true` are four different values, and so are `0`, `'0'`, `0.0`, `false`, `''`, `[]` and `null`. Use [`normalizeNumericStrings()` or `normalizeBooleans()`](#normalization) to treat some of them as equal.

### Null and Missing Keys

`['a' => null]` and `[]` give different fingerprints. Use `ignoreNulls()` to leave nulls out.

### Floats

- Floats are encoded from their exact IEEE-754 bits, so `0.1 + 0.2` and `0.3` differ. Use `precision()` to round them.
- `-0.0` is the same as `0.0`.
- `INF` and `-INF` are supported. `NAN` throws an `UnsupportedValueException`, because it is not equal to itself.

### Strings

Strings are compared byte for byte. Binary data, NUL bytes and invalid UTF-8 are fine; only the string rules (`lower()`, `unicode()`…) need valid UTF-8 and reject anything else.

### Keys

Integer and string keys are different: `[1 => 'x']` and `['a' => 'x']` never collide. PHP itself casts the key `'1'` to the integer `1`, so `['1' => 'x']` and `[1 => 'x']` are the same array and share a fingerprint.

### Objects and Dates

`stdClass` is a map, the same as the equivalent associative array. Dates are compared by their UTC instant, with microseconds. Other objects are covered in [Objects](#objects).

```php
Fingerprint::make((object) ['a' => 1]) === Fingerprint::make(['a' => 1]); // true
```

### What Is Rejected

Closures, resources, generators, `NAN`, objects that aren't supported, objects that contain themselves, and values nested deeper than the [maximum depth](#objects) throw an exception that names the path:

```php
Fingerprint::make(['x' => new DateTimeZone('UTC')]);
// UnsupportedValueException: An object of class DateTimeZone cannot be fingerprinted at path "x".
```

### Namespaces

`namespace()` keeps fingerprints of the same data used for different purposes apart. An `"invoice"` fingerprint never matches an `"order"` fingerprint of the same data, nor one without a namespace:

```php
Fingerprint::for($data)->namespace('invoice')->make();
```

A namespace is not a secret. Use [`hmac()`](#hashing) when the fingerprint must not be forgeable.
