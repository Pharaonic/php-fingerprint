## Lists

Read this when the order of a list, such as tags, shouldn't change the fingerprint, or when duplicates should be ignored.

Lists keep their order by default. List rules change that for every list, or only at a path after [`at()`](#paths). They have no effect on maps and scalars, so a global `unordered()` safely reaches every list.

### unordered()

Lists that hold the same elements, in any order, give the same fingerprint:

```php
$a = Fingerprint::for(['tags' => ['b', 'a']])->at('tags')->unordered()->make();
$b = Fingerprint::for(['tags' => ['a', 'b']])->at('tags')->unordered()->make();

$a === $b; // true
```

Elements are ordered by their canonical bytes. Duplicates still count: `['a', 'a', 'b']` and `['a', 'b']` differ.

### unique()

`unique()` removes repeated elements and keeps the first occurrence. Add it to `unordered()` when duplicates don't matter either:

```php
Fingerprint::for(['b', 'a', 'b'])->unordered()->unique()->result()->normalized(); // ['a', 'b']
```

Elements are compared strictly, so `1` and `'1'` are both kept:

```php
Fingerprint::for([1, '1', 1, 2])->unique()->result()->normalized(); // [1, '1', 2]
```

### sortBy()

`unordered()` compares whole elements. For a list of records, such as order items, it's often clearer to order them by one field, several fields, or fields with a direction. Fields may be dot paths inside each element, without wildcards:

```php
$byId = $builder->at('items')->sortBy('id');
$byCategory = $builder->at('items')->sortBy(['category', 'id']);
$byDirection = $builder->at('items')->sortBy(['category' => 'asc', 'priority' => 'desc']);
$byPosition = $builder->at('items')->sortBy('meta.position');
```

Two orders with the same items, listed in a different order, then match:

```php
$a = ['items' => [['id' => 2, 'qty' => 1], ['id' => 1, 'qty' => 3]]];
$b = ['items' => [['id' => 1, 'qty' => 3], ['id' => 2, 'qty' => 1]]];

Fingerprint::make($a) === Fingerprint::make($b); // false

$byId = Fingerprint::for(null)->at('items')->sortBy('id');
$byId->for($a)->make() === $byId->for($b)->make(); // true
```

Ties keep their input order. Values of different types are ordered by type: a missing field, then `null`, booleans, numbers, strings, then arrays.

### uniqueBy()

`uniqueBy($field)` removes records that repeat a field's value, keeping the first:

```php
Fingerprint::for([['id' => 1, 'v' => 'a'], ['id' => 1, 'v' => 'b']])
    ->uniqueBy('id')
    ->result()->normalized();
// [['id' => 1, 'v' => 'a']]
```

Lists are ordered first, then de-duplicated, so "first" means first in the final order. Elements without the field share one value for `uniqueBy()`.

### sortUsing()

When no field order fits, order elements with your own comparator, like `usort()`. It receives two elements as plain PHP values and must return an integer:

```php
$builder->at('items')->sortUsing(fn (array $a, array $b) => $a['rank'] <=> $b['rank']);
```

### Which Rule Wins

`unordered()`, `sortBy()` and `sortUsing()` replace each other for a path, and so do `unique()` and `uniqueBy()`. The last one declared wins.

That's the end of The Basics. You can now control which values count and when two values are equal.

**Next:** Going Further starts with [Hashing & HMAC](#hashing): secrets, other algorithms and output formats.
