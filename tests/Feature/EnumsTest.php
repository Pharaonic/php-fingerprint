<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Exceptions\UnsupportedValueException;
use Pharaonic\Fingerprint\Fingerprint;
use Pharaonic\Fingerprint\Tests\Fixtures\Enums\Level;
use Pharaonic\Fingerprint\Tests\Fixtures\Enums\Priority;
use Pharaonic\Fingerprint\Tests\Fixtures\Enums\State;
use Pharaonic\Fingerprint\Tests\Fixtures\Enums\Status;
use Pharaonic\Fingerprint\Tests\Fixtures\Enums\Suit;

it('keeps an enum case apart from its name and its backed value', function () {
    $case = Fingerprint::make(Status::Paid);

    expect($case)->not->toBe(Fingerprint::make('paid'))
        ->and($case)->not->toBe(Fingerprint::make('Paid'))
        ->and(Fingerprint::make(Priority::High))->not->toBe(Fingerprint::make(3));
});

it('never matches cases of different enums, even with the same backed value', function () {
    expect(Fingerprint::make(Status::Paid))->not->toBe(Fingerprint::make(State::Paid));
});

it('tells the cases of one enum apart', function () {
    expect(Fingerprint::make(Suit::Hearts))->not->toBe(Fingerprint::make(Suit::Spades))
        ->and(Fingerprint::make(Status::Paid))->not->toBe(Fingerprint::make(Status::Pending));
});

it('fingerprints enum cases nested in maps, lists and objects', function () {
    $array = ['order' => ['status' => Status::Paid, 'priorities' => [Priority::Low, Priority::High]]];
    $object = (object) ['order' => (object) ['priorities' => [Priority::Low, Priority::High], 'status' => Status::Paid]];

    expect(Fingerprint::make($object))->toBe(Fingerprint::make($array))
        ->and(Fingerprint::make([Priority::High, Priority::Low]))->not->toBe(Fingerprint::make([Priority::Low, Priority::High]));
});

it('uses case names with enumsByName()', function () {
    expect(Fingerprint::for(['status' => Status::Paid])->enumsByName()->make())->toBe(Fingerprint::make(['status' => 'Paid']))
        ->and(Fingerprint::for(Suit::Hearts)->enumsByName()->make())->toBe(Fingerprint::make('Hearts'));
});

it('uses backed values with enumsByValue()', function () {
    expect(Fingerprint::for(['status' => Status::Paid])->enumsByValue()->make())->toBe(Fingerprint::make(['status' => 'paid']))
        ->and(Fingerprint::for(Priority::High)->enumsByValue()->make())->toBe(Fingerprint::make(3));
});

it('lets the last enum mode win and scopes it with at()', function () {
    $value = ['status' => Status::Paid, 'state' => State::Paid];

    expect(Fingerprint::for($value)->enumsByName()->enumsByValue()->make())->toBe(Fingerprint::make(['status' => 'paid', 'state' => 'paid']))
        ->and(Fingerprint::for($value)->at('status')->enumsByValue()->make())->toBe(Fingerprint::make(['status' => 'paid', 'state' => State::Paid]));
});

it('applies string rules to the name or value an enum case becomes', function () {
    expect(Fingerprint::for(Status::Paid)->enumsByName()->lower()->make())->toBe(Fingerprint::make('paid'));
});

it('rejects enumsByValue() for a case without a backed value', function () {
    Fingerprint::for(['card' => Suit::Hearts])->enumsByValue()->make();
})->throws(
    UnsupportedValueException::class,
    'The enum case Pharaonic\Fingerprint\Tests\Fixtures\Enums\Suit::Hearts has no backed value for enumsByValue() at path "card".',
);

it('lets a normalize() callback convert enum cases', function () {
    $builder = Fingerprint::for(['status' => Status::Paid])->normalize(Status::class, fn(Status $status) => strtoupper($status->value));

    expect($builder->make())->toBe(Fingerprint::make(['status' => 'PAID']));
});

it('keeps an enum case that implements JsonSerializable as a case', function () {
    expect(Fingerprint::make(Level::Debug))->not->toBe(Fingerprint::make(['level' => 'Debug']))
        ->and(Fingerprint::for(Level::Debug)->enumsByValue()->make())->toBe(Fingerprint::make(1));
});

it('orders and de-duplicates enum cases deterministically', function () {
    $sorted = Fingerprint::for([Suit::Spades, Suit::Hearts, Suit::Spades])->unordered()->unique();

    expect($sorted->make())->toBe(Fingerprint::make([Suit::Hearts, Suit::Spades]))
        ->and(Fingerprint::for([Suit::Hearts, Suit::Spades])->unordered()->make())
        ->toBe(Fingerprint::for([Suit::Spades, Suit::Hearts])->unordered()->make());
});

it('sorts and de-duplicates by enum fields', function () {
    $orders = [['id' => 1, 'status' => Status::Pending], ['id' => 2, 'status' => Status::Paid], ['id' => 3, 'status' => Status::Paid]];

    $result = Fingerprint::for($orders)->sortBy('status')->uniqueBy('status')->result()->normalized();

    expect(array_column($result, 'id'))->toBe([2, 1]);
});

it('passes enum cases to transform() and ignoreWhen() callbacks', function () {
    $seen = [];

    Fingerprint::for(['status' => Status::Paid])
        ->ignoreWhen(function (string $path, mixed $value) use (&$seen) {
            $seen[] = $value;

            return false;
        })
        ->at('status')->transform(function (mixed $value) use (&$seen) {
            $seen[] = $value;

            return $value;
        })
        ->make();

    expect($seen)->toBe([Status::Paid, Status::Paid]);
});

it('keeps enum cases in the normalized result', function () {
    expect(Fingerprint::for(['status' => Status::Paid])->result()->normalized())->toBe(['status' => Status::Paid]);
});

it('drops enum cases with ignore() and keeps them with only()', function () {
    $value = ['status' => Status::Paid, 'id' => 1];

    expect(Fingerprint::for($value)->ignore('status')->make())->toBe(Fingerprint::make(['id' => 1]))
        ->and(Fingerprint::for($value)->only(['status'])->make())->toBe(Fingerprint::make(['status' => Status::Paid]));
});

it('signs the backed value of an enum case', function () {
    $payload = ['status' => Status::Paid, 'priority' => Priority::High];

    expect(Fingerprint::signature($payload)->fields(['status', 'priority'])->message())->toBe('paid3');
});

it('signs case names with enumsByName()', function () {
    $payload = ['suit' => Suit::Hearts, 'status' => Status::Paid];

    expect(Fingerprint::signature($payload)->fields(['suit', 'status'])->enumsByName()->message())->toBe('HeartsPaid')
        ->and(Fingerprint::signature($payload)->fields(['suit', 'status'])->at('suit')->enumsByName()->message())->toBe('Heartspaid');
});

it('rejects a pure enum case in a signature', function () {
    Fingerprint::signature(['suit' => Suit::Hearts])->fields(['suit'])->message();
})->throws(
    UnsupportedValueException::class,
    'The field "suit" holds Pharaonic\Fingerprint\Tests\Fixtures\Enums\Suit; use enumsByName() or transform().',
);

it('rejects enumsByValue() for a pure enum case in a signature', function () {
    Fingerprint::signature(['suit' => Suit::Hearts])->fields(['suit'])->enumsByValue()->message();
})->throws(UnsupportedValueException::class, 'has no backed value for enumsByValue() at path "suit".');
