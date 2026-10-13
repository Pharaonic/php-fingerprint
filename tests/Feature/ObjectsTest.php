<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Exceptions\CircularReferenceException;
use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Exceptions\UnsupportedValueException;
use Pharaonic\Fingerprint\Fingerprint;

function jsonOf(mixed $value): JsonSerializable
{
    return new class ($value) implements JsonSerializable {
        public function __construct(public mixed $value) {}

        public function jsonSerialize(): mixed
        {
            return $this->value;
        }
    };
}

it('treats stdClass like the equivalent associative array', function () {
    expect(Fingerprint::make((object) ['a' => 1, 'b' => (object) ['c' => [1, 2]]]))
        ->toBe(Fingerprint::make(['b' => ['c' => [1, 2]], 'a' => 1]));
});

it('keeps an empty stdClass apart from an empty list', function () {
    expect(Fingerprint::make(new stdClass()))->not->toBe(Fingerprint::make([]));
});

it('uses what JsonSerializable returns, recursively', function () {
    expect(Fingerprint::make(jsonOf('x')))->toBe(Fingerprint::make('x'))
        ->and(Fingerprint::make(jsonOf(jsonOf(['a' => 1]))))->toBe(Fingerprint::make(['a' => 1]));
});

it('iterates Traversable objects', function () {
    $aggregate = new class implements IteratorAggregate {
        public function getIterator(): Iterator
        {
            return new ArrayIterator(['b' => 2, 'a' => 1]);
        }
    };

    expect(Fingerprint::make($aggregate))->toBe(Fingerprint::make(['a' => 1, 'b' => 2]))
        ->and(Fingerprint::make(new ArrayObject([1, 2])))->toBe(Fingerprint::make([1, 2]));
});

it('rejects generators', function () {
    $generator = (function () {
        yield 1;
    })();

    (void) Fingerprint::make(['g' => $generator]);
})->throws(UnsupportedValueException::class, 'A generator cannot be fingerprinted at path "g".');

it('rejects iterators that repeat a key', function () {
    $iterator = new class implements Iterator {
        private int $i = 0;

        public function current(): mixed
        {
            return $this->i;
        }

        public function key(): mixed
        {
            return 'same';
        }

        public function next(): void
        {
            $this->i++;
        }

        public function rewind(): void
        {
            $this->i = 0;
        }

        public function valid(): bool
        {
            return $this->i < 2;
        }
    };

    (void) Fingerprint::make($iterator);
})->throws(UnsupportedValueException::class, 'yielding the key "same" twice cannot be fingerprinted at the root.');

it('detects objects that contain themselves', function () {
    $node = new stdClass();
    $node->child = new stdClass();
    $node->child->parent = $node;

    (void) Fingerprint::make(['root' => $node]);
})->throws(CircularReferenceException::class, 'An object of class stdClass contains itself at path "root.child.parent".');

it('detects a JsonSerializable that returns itself', function () {
    $self = jsonOf(null);
    $self->value = $self;

    (void) Fingerprint::make($self);
})->throws(CircularReferenceException::class);

it('accepts the same object twice when it does not contain itself', function () {
    $shared = (object) ['x' => 1];

    expect(Fingerprint::make([$shared, $shared]))->toBe(Fingerprint::make([['x' => 1], ['x' => 1]]));
});

it('converts objects with a normalize() callback', function () {
    $hash = Fingerprint::for(['money' => new ArrayObject(['amount' => 100, 'currency' => 'EGP'])])
        ->normalize(ArrayObject::class, fn(ArrayObject $money) => $money['amount'] . ' ' . $money['currency'])
        ->make();

    expect($hash)->toBe(Fingerprint::make(['money' => '100 EGP']));
});

it('prefers an exact class, then the first matching type in declaration order', function () {
    $value = new ArrayIterator([]);

    $hash = fn($builder) => $builder->make();
    $builder = Fingerprint::for($value)
        ->normalize(Traversable::class, fn() => 'traversable')
        ->normalize(Countable::class, fn() => 'countable');

    expect($hash($builder))->toBe(Fingerprint::make('traversable'))
        ->and($hash($builder->normalize(ArrayIterator::class, fn() => 'exact')))->toBe(Fingerprint::make('exact'));
});

it('opts Stringable objects in through normalize()', function () {
    $stringable = new class {
        public function __toString(): string
        {
            return 'hello';
        }
    };

    expect(fn() => Fingerprint::make($stringable))->toThrow(UnsupportedValueException::class)
        ->and(Fingerprint::for($stringable)->normalize(Stringable::class, fn($value) => (string) $value)->make())
        ->toBe(Fingerprint::make('hello'));
});

it('applies normalize() everywhere whatever the scope', function () {
    $hash = Fingerprint::for(['a' => new DateTimeZone('UTC')])
        ->at('b')
        ->normalize(DateTimeZone::class, fn(DateTimeZone $zone) => $zone->getName())
        ->make();

    expect($hash)->toBe(Fingerprint::make(['a' => 'UTC']));
});

it('detects a normalize() callback that returns its own input', function () {
    (void) Fingerprint::for(new DateTimeZone('UTC'))->normalize(DateTimeZone::class, fn($zone) => $zone)->make();
})->throws(CircularReferenceException::class);

it('rejects normalize() for a class that does not exist', function () {
    (void) Fingerprint::for(null)->normalize('Missing\\Money', fn() => null);
})->throws(ConfigurationException::class, 'normalize() needs an existing class or interface, "Missing\\Money" given.');

it('gives callbacks a stdClass as its array of properties', function () {
    $seen = null;

    (void) Fingerprint::for(['o' => (object) ['a' => 1]])
        ->ignoreWhen(function (string $path, mixed $value) use (&$seen) {
            if ($path === 'o') {
                $seen = $value;
            }

            return false;
        })
        ->make();

    expect($seen)->toBe(['a' => 1]);
});

it('normalizes objects returned by a transform', function () {
    expect(Fingerprint::for(['x' => 'x'])->at('x')->transform(fn() => (object) ['a' => 1])->make())
        ->toBe(Fingerprint::make(['x' => ['a' => 1]]));
});

it('does not mistake a new object for an active one that was freed', function () {
    $hash = Fingerprint::for(['x' => 1, 'y' => 2])
        ->at('*')->transform(fn($value) => jsonOf(jsonOf((object) ['v' => $value])))
        ->make();

    expect($hash)->toBe(Fingerprint::make(['x' => ['v' => 1], 'y' => ['v' => 2]]));
});
