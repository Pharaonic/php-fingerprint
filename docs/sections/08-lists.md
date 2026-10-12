## Lists

Lists keep their order by default. List rules change that for every list, or only at a path after [`at()`](#paths). They have no effect on maps and scalars, so a global `unordered()` safely reaches every list.

### unordered()

Elements are ordered by their canonical bytes, so lists that hold the same elements, duplicates included, give the same fingerprint:

```php
$a = Fingerprint::for(['tags' => ['b', 'a']])->at('tags')->unordered()->make();
$b = Fingerprint::for(['tags' => ['a', 'b']])->at('tags')->unordered()->make();

$a === $b; // true
```

`['a', 'a', 'b']` and `['a', 'b']` still differ. Add `unique()` when duplicates don't matter either.

### sortBy()

Order structured elements by one field, several fields, or fields with a direction. Fields may be dot paths inside each element, without wildcards:

```php
$builder->at('items')->sortBy('id');
$builder->at('items')->sortBy(['category', 'id']);
$builder->at('items')->sortBy(['category' => 'asc', 'priority' => 'desc']);
$builder->at('items')->sortBy('meta.position');
```

Ties keep their input order. Values of different types are ordered by type: a missing field, then `null`, booleans, numbers, strings, then arrays.

### sortUsing()

Order elements with a comparator, like `usort()`. It receives two elements as plain PHP values and must return an integer:

```php
$builder->at('items')->sortUsing(fn (array $a, array $b) => $a['rank'] <=> $b['rank']);
```

### unique() and uniqueBy()

`unique()` removes repeated elements, compared strictly, so `1` and `'1'` are both kept. `uniqueBy($field)` removes elements that repeat a field's value. Both keep the first occurrence:

```php
Fingerprint::for([1, '1', 1, 2])->unique()->result()->normalized(); // [1, '1', 2]

Fingerprint::for([['id' => 1, 'v' => 'a'], ['id' => 1, 'v' => 'b']])
    ->uniqueBy('id')
    ->result()->normalized();
// [['id' => 1, 'v' => 'a']]
```

Lists are ordered first, then de-duplicated, so "first" means first in the final order. Elements without the field share one value for `uniqueBy()`.

### Which Rule Wins

`unordered()`, `sortBy()` and `sortUsing()` replace each other for a path, and so do `unique()` and `uniqueBy()`. The last one declared wins.
