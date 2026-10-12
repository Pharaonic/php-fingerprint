<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Fingerprint;

it('ignores element order in unordered lists, duplicates included', function () {
    $hash = fn (array $tags) => Fingerprint::for(['tags' => $tags])->at('tags')->unordered()->make();

    expect($hash(['php', 'laravel', 'php']))->toBe($hash(['laravel', 'php', 'php']))
        ->and($hash([1, 1, 2]))->not->toBe($hash([1, 2, 2]))
        ->and($hash([1, 2]))->not->toBe($hash([1, 2, 2]))
        ->and($hash([1]))->not->toBe($hash(['1']));
});

it('makes every list unordered when declared globally, nested ones included', function () {
    $a = ['x' => [[3, 1], [2]], 'y' => ['b', 'a']];
    $b = ['y' => ['a', 'b'], 'x' => [[2], [1, 3]]];

    expect(Fingerprint::for($a)->unordered()->make())->toBe(Fingerprint::for($b)->unordered()->make())
        ->and(Fingerprint::make($a))->not->toBe(Fingerprint::make($b));
});

it('leaves maps and scalars alone', function () {
    $value = ['m' => ['b' => 2, 'a' => 1], 's' => 'x'];

    expect(Fingerprint::for($value)->unordered()->unique()->sortBy('id')->make())->toBe(Fingerprint::make($value));
});

it('sorts structured items by a field, ascending or descending', function () {
    $items = [['id' => 2], ['id' => 3], ['id' => 1]];

    expect(Fingerprint::for($items)->sortBy('id')->make())->toBe(Fingerprint::make([['id' => 1], ['id' => 2], ['id' => 3]]))
        ->and(Fingerprint::for($items)->sortBy(['id' => 'desc'])->make())->toBe(Fingerprint::make([['id' => 3], ['id' => 2], ['id' => 1]]))
        ->and(Fingerprint::for($items)->sortBy(['id'])->make())->toBe(Fingerprint::for($items)->sortBy('id')->make());
});

it('sorts by a dot path inside each element', function () {
    $items = [['meta' => ['rank' => 2], 'n' => 'b'], ['meta' => ['rank' => 1], 'n' => 'a']];

    expect(Fingerprint::for($items)->sortBy('meta.rank')->make())->toBe(Fingerprint::make(array_reverse($items)));
});

it('sorts mixed types in one fixed order: missing, null, bool, number, string, array', function () {
    $items = [['k' => 'a'], ['k' => [1]], ['k' => 2.5], ['k' => true], ['x' => 1], ['k' => null], ['k' => 2], ['k' => 2.0]];
    $sorted = [['x' => 1], ['k' => null], ['k' => true], ['k' => 2], ['k' => 2.0], ['k' => 2.5], ['k' => 'a'], ['k' => [1]]];

    expect(Fingerprint::for($items)->sortBy('k')->make())->toBe(Fingerprint::make($sorted));
});

it('keeps the input order of ties', function () {
    $a = [['id' => 1, 'v' => 'x'], ['id' => 1, 'v' => 'y']];
    $b = [['id' => 1, 'v' => 'y'], ['id' => 1, 'v' => 'x']];

    expect(Fingerprint::for($a)->sortBy('id')->make())->not->toBe(Fingerprint::for($b)->sortBy('id')->make())
        ->and(Fingerprint::for($a)->sortBy(['id', 'v'])->make())->toBe(Fingerprint::for($b)->sortBy(['id', 'v'])->make());
});

it('sorts with a comparator that receives plain PHP values', function () {
    $received = [];

    $hash = Fingerprint::for([['n' => 2], ['n' => 1]])
        ->sortUsing(function ($a, $b) use (&$received) {
            $received[] = $a;

            return $a['n'] <=> $b['n'];
        })
        ->make();

    expect($hash)->toBe(Fingerprint::make([['n' => 1], ['n' => 2]]))
        ->and($received[0])->toBeArray();
});

it('rejects a comparator that does not return an integer', function () {
    Fingerprint::for(['l' => [2, 1]])->at('l')->sortUsing(fn ($a, $b) => $a > $b)->make();
})->throws(ConfigurationException::class, 'A sortUsing() comparator must return an integer, bool returned at path "l".');

it('removes repeated elements strictly', function () {
    expect(Fingerprint::for([1, '1', 1, 1.0])->unique()->make())->toBe(Fingerprint::make([1, '1', 1.0]));
});

it('removes elements repeating a field, keeping the first', function () {
    $items = [['id' => 1, 'v' => 'a'], ['id' => 2], ['id' => 1, 'v' => 'b'], ['v' => 'c'], ['v' => 'd']];

    expect(Fingerprint::for($items)->uniqueBy('id')->make())
        ->toBe(Fingerprint::make([['id' => 1, 'v' => 'a'], ['id' => 2], ['v' => 'c']]));
});

it('lets the last ordering rule win', function () {
    $items = [['id' => 2, 'n' => 'a'], ['id' => 1, 'n' => 'b']];

    expect(Fingerprint::for($items)->unordered()->sortBy(['id' => 'desc'])->make())->toBe(Fingerprint::make($items))
        ->and(Fingerprint::for($items)->sortBy('id')->sortUsing(fn () => 0)->make())->toBe(Fingerprint::make($items));
});

$invalid = [
    'no fields' => fn ($b) => $b->sortBy([]),
    'wildcard field' => fn ($b) => $b->sortBy('items.*'),
    'bad direction' => fn ($b) => $b->sortBy(['id' => 'down']),
    'non-string field' => fn ($b) => $b->sortBy([['id']]),
    'empty field' => fn ($b) => $b->sortBy(''),
    'wildcard uniqueBy' => fn ($b) => $b->uniqueBy('*'),
];

it('rejects invalid sort and unique fields', function (string $case) use ($invalid) {
    $invalid[$case](Fingerprint::for([]));
})->throws(ConfigurationException::class)->with(array_keys($invalid));
