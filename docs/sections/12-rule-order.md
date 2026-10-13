## How Rules Combine

Read this when several rules reach the same value and the result isn't what you expected. It brings together the rules from the previous pages, so read those first.

### The Last Rule of a Kind Wins

Rules of different kinds combine: `->at('email')->trim()->lower()` does both. For the same kind of rule, the last one declared for a path wins, so a path rule overrides a global rule of the same kind:

```php
Fingerprint::for($user)
    ->lower()                  // every string
    ->at('code')->upper()      // code is upper case, not lower case
    ->make();
```

These rules are one kind each:

- `lower()` and `upper()`;
- `unordered()`, `sortBy()` and `sortUsing()`;
- `unique()` and `uniqueBy()`.

A global rule also applies to the root value itself:

```php
Fingerprint::for('  Ahmed ')->trim()->result()->normalized(); // "Ahmed"
```

### Rules Run in a Fixed Order

Rules always run in the same order, whatever order you declare them in, so `->lower()->trim()` and `->trim()->lower()` are the same. For each value:

1. objects become plain data ([Objects](#objects));
2. `ignoreWhen()` callbacks, which see the value before any value rule;
3. `transform()`, whose result is converted again;
4. `enumsByName()` or `enumsByValue()`;
5. string rules: `trim()`, `normalizeWhitespace()`, `unicode()`, then `lower()` or `upper()`;
6. `normalizeNumericStrings()`, then `precision()`;
7. `normalizeBooleans()`;
8. date rules: `datePrecision()` and `keepTimezone()`;
9. the value's children;
10. list rules: ordering, then de-duplication.

`ignoreNulls()`, `ignoreEmptyStrings()` and `ignoreEmptyArrays()` check each value after its rules apply.

Because a list's rules run after its children (9 and 10 above), you can clean each element and then order the list:

```php
Fingerprint::for($contact)
    ->at('phones.*')->transform(fn (string $phone) => preg_replace('/\D/', '', $phone))
    ->at('phones')->unordered()->unique()
    ->make();
```

### Paths Refer to the Input

Paths always refer to the **input** keys. When a filter removes an element, the next one keeps its input index for every other rule, even though the list is re-indexed in the result:

```php
Fingerprint::for(['items' => ['a', ' b ', ' c ']])
    ->ignore('items.0')
    ->at('items.1')->trim()     // still ' b ', the second input element
    ->result()->normalized();
// ['items' => ['b', ' c ']]
```

That's the end of Going Further.

**Next:** [Ordered Signatures](#signatures): verify payment gateway callbacks and webhooks.
