## Objects

Read this when your data holds objects other than plain arrays: models, value objects, collections. `stdClass` and dates already work, as [How Matching Works](#canonical-rules) showed.

Objects are converted into plain data, in this order:

1. closures and generators are rejected;
2. a `normalize()` callback for the object's class or one of its types;
3. `DateTimeInterface` stays a date (see [Dates](#normalization));
4. an enum case stays an enum case, even when its enum implements `JsonSerializable` (see [Enums](#normalization));
5. `JsonSerializable` uses what `jsonSerialize()` returns;
6. `Traversable` (`ArrayObject`, `ArrayIterator`, `IteratorAggregate`…) is iterated;
7. `stdClass` becomes a map, even when empty or numerically keyed;
8. anything else throws an `UnsupportedValueException`.

Private and protected properties are never read. What a conversion returns is converted again, so a `JsonSerializable` may return another one.

A value object that already implements `JsonSerializable` needs nothing more:

```php
final class Money implements JsonSerializable
{
    public function __construct(private int $amount, private string $currency) {}

    public function jsonSerialize(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }
}

Fingerprint::for(['total' => new Money(1050, 'EGP')])->result()->normalized();
// ['total' => ['amount' => 1050, 'currency' => 'EGP']]
```

Use a custom normalizer for classes you can't change, or when you want a different shape than `jsonSerialize()`.

### Custom Normalizers

`normalize()` converts objects of a class, or implementing an interface, with your callback. It applies everywhere, whatever `at()` scope is active:

```php
use App\Models\Money;

Fingerprint::for($invoice)
    ->normalize(Money::class, fn (Money $money) => [$money->amount(), $money->currency()])
    ->make();
```

An exact class match wins. Otherwise the first matching class or interface wins, in declaration order. `Stringable` objects are rejected by default; opt them in with:

```php
->normalize(Stringable::class, fn (Stringable $value) => (string) $value)
```

### Traversable Objects

Iterated keys must be integers or strings, and must not repeat, or an `UnsupportedValueException` is thrown. Generators are always rejected, because iterating them runs code and can only be done once.

### Circular References

An object that contains itself throws a `CircularReferenceException` that names the path. The same object used twice in different places is fine:

```php
$node = new ArrayObject();
$node['self'] = $node;

Fingerprint::make($node);
// CircularReferenceException: An object of class ArrayObject contains itself at path "self".
```

### Maximum Depth

Values may nest 512 levels deep by default, like `json_decode()`. Deeper values throw a `MaximumDepthExceededException` instead of exhausting the stack; arrays that reference themselves hit this limit too. Change it with `maxDepth()`:

```php
Fingerprint::for($tree)->maxDepth(64)->make();
```

Objects count towards the depth. `maxDepth()` must be at least 1.

**Next:** [How Rules Combine](#rule-order): what happens when several rules reach the same value.
