## How Matching Works

Read this when two values you expect to match get different fingerprints, or the other way round. It needs nothing beyond the [Quick Start](#quick-start).

Before hashing, every value is written in a canonical form (format v1). Three rules cover almost every case: key order never matters, list order does, and types are kept.

### Key Order Never Matters

An associative array (a **map**) gives the same fingerprint whatever the order of its keys, at every level:

```php
Fingerprint::make(['b' => 1, 'a' => 2]) === Fingerprint::make(['a' => 2, 'b' => 1]); // true
```

### List Order Matters

An array whose keys are exactly `0, 1, 2, …` in that order is a **list**, and its order is part of the data:

```php
Fingerprint::make([1, 2]) === Fingerprint::make([2, 1]); // false
```

`[]` is an empty list. When a list's order doesn't matter to you, [Lists](#lists) shows how to ignore it.

### Types Are Kept

`1`, `'1'`, `1.0` and `true` are four different values, and so are `0`, `'0'`, `0.0`, `false`, `''`, `[]` and `null`:

```php
Fingerprint::make(['id' => 7]) === Fingerprint::make(['id' => '7']); // false
```

[Normalization](#normalization) shows how to treat some of them as equal.

### Null Is Not a Missing Key

`['a' => null]` and `[]` give different fingerprints. [Filtering](#filtering) shows how to leave nulls out.

### Seeing What Gets Hashed

`result()->normalized()` returns the value exactly as it is fingerprinted, after every rule you added. The next pages use it to show what each rule does:

```php
Fingerprint::for(['name' => 'Ahmed', 'updated_at' => '2026-10-12'])
    ->ignore('updated_at')
    ->result()->normalized();
// ['name' => 'Ahmed']
```

[Debugging](#debugging) covers everything else `result()` holds.

### Edge Cases

You can skip this part on a first read and come back when a value surprises you.

**Floats** are encoded from their exact IEEE-754 bits, so `0.1 + 0.2` and `0.3` differ; use `precision()` to round them. `-0.0` is the same as `0.0`. `INF` and `-INF` are supported. `NAN` throws an `UnsupportedValueException`, because it is not equal to itself.

**Strings** are compared byte for byte. Binary data, NUL bytes and invalid UTF-8 are fine; only the string rules (`lower()`, `unicode()`…) need valid UTF-8 and reject anything else.

**Keys:** integer and string keys are different, so `[1 => 'x']` and `['a' => 'x']` never collide. PHP itself casts the key `'1'` to the integer `1`, so `['1' => 'x']` and `[1 => 'x']` are the same array and share a fingerprint.

**Maps and lists after filtering:** a list stays a list after filters remove elements, and is re-indexed. A map stays a map, even when filters empty it, so it doesn't match `[]`. A sparse array such as `[1 => 'a', 0 => 'b']` is a map.

**Objects and dates:** `stdClass` is a map, the same as the equivalent associative array. Dates are compared by their UTC instant, with microseconds. Other objects are covered in [Objects](#objects).

```php
Fingerprint::make((object) ['a' => 1]) === Fingerprint::make(['a' => 1]); // true
```

**What is rejected:** closures, resources, generators, `NAN`, objects that aren't supported, objects that contain themselves, and values nested deeper than the [maximum depth](#objects) throw an exception that names the path:

```php
Fingerprint::make(['x' => new DateTimeZone('UTC')]);
// UnsupportedValueException: An object of class DateTimeZone cannot be fingerprinted at path "x".
```

**Next:** [Filtering](#filtering): choose which values take part in the fingerprint.
