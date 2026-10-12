## Paths & Wildcards

Read this when you need to target nested values, like every item's price, or apply a rule to one field only.

`ignore()`, `only()`, `at()`, `sortBy()`, `uniqueBy()` and the signature `fields()` take dot paths. The syntax is the same as in [pharaonic/php-dot-array](https://github.com/Pharaonic/php-dot-array).

### Syntax

| Path | Matches |
| --- | --- |
| `user.email` | `$value['user']['email']` |
| `items.0.price` | the price of the first item |
| `items.*.price` | the price of every item: `*` matches any one key or list index |
| `*.id` | the `id` of every top-level entry |
| `config.app\.name` | the key `app.name` inside `config` |
| `tags.\*` | the key that is literally `*` |
| `path\\to` | the key `path\to` |

In a double-quoted PHP string, write the backslash twice: `"config.app\\.name"`.

Paths are validated strictly and never trimmed: an empty path, or one with an empty segment (`a..b`, `.a`, `a.`), throws a `ConfigurationException` when you declare it. A path that matches nothing is simply a no-op.

Paths always refer to the **input** keys. When a filter removes `items.1`, the next element is still `items.2` for every other rule, even though the list is re-indexed in the result.

### Scoping Rules With at()

Value rules (`trim()`, `lower()`, `precision()`, `transform()`, `unordered()`…) apply to every value until you call `at()`. After `at()`, they apply to the values at that path, until the next `at()`:

```php
Fingerprint::for($user)
    ->trim()                       // every string
    ->at('email')->lower()         // only email
    ->at('roles')->unordered()     // only roles
    ->make();
```

- Rules of different kinds combine: `->at('email')->trim()->lower()` does both.
- For the same kind of rule, the last one declared for a path wins, so a path rule overrides a global rule of the same kind. `lower()` and `upper()` are one kind, and so are `unordered()`, `sortBy()` and `sortUsing()`, and `unique()` and `uniqueBy()`.
- A global rule also applies to the root value itself.

### Rule Order

Rules always run in the same order, whatever order you declare them in, so `->lower()->trim()` and `->trim()->lower()` are the same:

1. objects become plain data ([Objects](#objects));
2. `ignoreWhen()` callbacks;
3. `transform()`, whose result is converted again;
4. string rules: `trim()`, `normalizeWhitespace()`, `unicode()`, then `lower()` or `upper()`;
5. `normalizeNumericStrings()`, then `precision()`;
6. `normalizeBooleans()`;
7. date rules: `datePrecision()` and `keepTimezone()`;
8. the value's children;
9. list rules: ordering, then de-duplication.
