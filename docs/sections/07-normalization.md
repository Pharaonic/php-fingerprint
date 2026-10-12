## Normalization

Read this when equal-looking data should match: extra spaces, upper and lower case, rounding, numbers stored as strings, or dates in different timezones.

Value rules turn equivalent values into the same value before hashing. Each applies to every value of its type, or only at a path after [`at()`](#paths). The page goes from the most common rules to the most flexible one, `transform()`.

### Strings

| Rule | Effect |
| --- | --- |
| `trim()` | Removes ASCII whitespace (space, tab, line feed, carriage return, vertical tab, form feed) from both ends |
| `normalizeWhitespace()` | Turns every run of ASCII whitespace into one space, with none at either end |
| `lower()` / `upper()` | Changes case with mbstring, in UTF-8 |
| `unicode($form)` | Applies a Unicode normalization form: `NFC`, `NFD`, `NFKC` or `NFKD` |

```php
Fingerprint::for(['  Ahmed  ', "a \t\n b", 'ÉCOLE'])
    ->trim()->normalizeWhitespace()->lower()
    ->result()->normalized();
// ['ahmed', 'a b', 'école']
```

These rules never depend on the locale. They need valid UTF-8 and throw an `UnsupportedValueException` for anything else instead of silently replacing bytes. `unicode()` needs `ext-intl` or `symfony/polyfill-intl-normalizer`, and throws a `MissingExtensionException` without either.

### Numbers

`precision($digits)` rounds floats to 0 to 53 decimals, from their exact binary value, the same way on every PHP version:

```php
Fingerprint::for(0.1 + 0.2)->precision(2)->make() === Fingerprint::for(0.3)->precision(2)->make(); // true

Fingerprint::for(0.285)->precision(2)->result()->normalized(); // 0.28
```

`0.285` is stored as `0.28499999…`, so it becomes `0.28`, not `0.29`. Integers, `INF` and `-INF` are left as they are.

`normalizeNumericStrings()` turns plain decimal strings into numbers. Integers that fit become `int`, everything else `float`:

```php
Fingerprint::for(['1', '1.50', '1e3', ' 1', '01', '+1'])
    ->normalizeNumericStrings()
    ->result()->normalized();
// [1, 1.5, 1000.0, ' 1', '01', '+1']
```

Only an optional minus sign, digits without leading zeros, an optional fraction and an optional exponent qualify: `' 1'`, `'+1'`, `'01'`, `'.5'` and `'1.'` stay strings.

### Booleans

`normalizeBooleans()` turns `true`, `1`, `'1'` and `'true'` into `true`, and `false`, `0`, `'0'` and `'false'` into `false`. Anything else is left alone:

```php
Fingerprint::for([1, '1', 'true', 0, 'false', 'yes'])->normalizeBooleans()->result()->normalized();
// [true, true, true, false, false, 'yes']
```

### Dates

`DateTimeInterface` values (`DateTime`, `DateTimeImmutable`, Carbon…) are compared by their UTC instant, with microseconds. The same moment in two timezones gives one fingerprint:

```php
$cairo = new DateTimeImmutable('2026-10-12 10:00:00', new DateTimeZone('Africa/Cairo'));
$utc = new DateTime('2026-10-12 07:00:00', new DateTimeZone('UTC'));

Fingerprint::make($cairo) === Fingerprint::make($utc); // true
```

| Rule | Effect |
| --- | --- |
| `datePrecision($unit)` | Truncates to `microsecond` (the default), `second`, `minute`, `hour` or `day`, in UTC |
| `keepTimezone()` | Compares the local time and timezone name instead of the instant |

```php
Fingerprint::for($cairo)->datePrecision('day')->result()->normalized(); // "2026-10-12T00:00:00.000000Z"
Fingerprint::for($cairo)->keepTimezone()->result()->normalized();       // "2026-10-12T10:00:00.000000 Africa/Cairo"
```

The original date objects are never changed.

:::warning Named timezones
With `keepTimezone()`, or when you build dates from local times in named zones, results depend on the timezone database of the PHP build, which changes when countries change their rules. Fixed offsets such as `+02:00` never change.
:::

### transform()

`transform()` replaces a value with your callback's result before the built-in rules run. The callback receives the value and its escaped path. The result is normalized like any other value, so it may be an array:

```php
Fingerprint::for(['phone' => '+20 100 000 0000'])
    ->at('phone')->transform(fn (string $phone) => preg_replace('/\D/', '', $phone))
    ->result()->normalized();
// ['phone' => '201000000000']
```

**Next:** [Lists](#lists): ignore the order of a list, or its duplicates.
