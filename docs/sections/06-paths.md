## Paths & Wildcards

Read this when you need to target nested values, like every item's price, or apply a rule to one field only. It builds on [Filtering](#filtering).

### Dot Paths

Join keys with dots to reach inside nested arrays. List indexes are keys too:

| Path | Matches |
| --- | --- |
| `user.email` | `$value['user']['email']` |
| `items.0.price` | the price of the first item |

```php
Fingerprint::for($order)->ignore(['customer.updated_at', 'items.0.note'])->make();
```

`only()` with a nested path also keeps the arrays leading to it:

```php
Fingerprint::for([
    'user' => ['email' => 'a@example.com', 'name' => 'Ahmed'],
    'meta' => ['seen' => 3],
])->only(['user.email'])->result()->normalized();
// ['user' => ['email' => 'a@example.com']]
```

### Wildcards

`*` matches any one key or list index, so a single path can reach many values:

| Path | Matches |
| --- | --- |
| `items.*.price` | the price of every item |
| `*.id` | the `id` of every top-level entry |

```php
Fingerprint::for($order)->ignore('items.*.cached_label')->make();
```

A path that matches nothing is simply a no-op.

### Scoping Rules With at()

So far every path went into a filter. The value rules you'll meet next, such as `trim()`, `lower()` or `unordered()`, apply to every value until you call `at()`. After `at()`, they apply only to the values at that path, until the next `at()`:

```php
Fingerprint::for($user)
    ->trim()                       // every string
    ->at('email')->lower()         // only email
    ->at('roles')->unordered()     // only roles
    ->make();
```

Rules after one `at()` combine: `->at('email')->trim()->lower()` does both. Filters such as `ignore()` and `only()` already take their own paths, so `at()` never scopes them.

Declare global rules first, then the `at()` ones. [How Rules Combine](#rule-order) explains what happens when rules overlap.

### Special Characters in Keys

You only need this when a key itself contains a dot, a `*` or a backslash. Escape it with a backslash:

| Path | Matches |
| --- | --- |
| `config.app\.name` | the key `app.name` inside `config` |
| `tags.\*` | the key that is literally `*` |
| `path\\to` | the key `path\to` |

In a double-quoted PHP string, write the backslash twice: `"config.app\\.name"`.

Paths are validated strictly and never trimmed: an empty path, or one with an empty segment (`a..b`, `.a`, `a.`), throws a `ConfigurationException` when you declare it. The syntax is the same as in [pharaonic/php-dot-array](https://github.com/Pharaonic/php-dot-array).

**Next:** [Normalization](#normalization): make equal-looking values match.
