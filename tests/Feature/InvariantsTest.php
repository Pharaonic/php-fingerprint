<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Fingerprint;

it('is deterministic', function () {
    $value = ['user' => ['id' => 7, 'tags' => ['a', 'b']], 'total' => 9.99, 'paid' => false];

    expect(Fingerprint::make($value))
        ->toBe(Fingerprint::make($value))
        ->toBe(Fingerprint::for($value)->make());
});

it('ignores associative key order', function () {
    expect(Fingerprint::make(['a' => 1, 'b' => 2]))
        ->toBe(Fingerprint::make(['b' => 2, 'a' => 1]));
});

it('ignores associative key order at every nesting level', function () {
    $a = ['x' => ['p' => 1, 'q' => ['m' => [['k' => 1, 'l' => 2]], 'n' => null]], 'y' => 'z'];
    $b = ['y' => 'z', 'x' => ['q' => ['n' => null, 'm' => [['l' => 2, 'k' => 1]]], 'p' => 1]];

    expect(Fingerprint::make($a))->toBe(Fingerprint::make($b));
});

it('keeps list order significant', function (array $a, array $b) {
    expect(Fingerprint::make($a))->not->toBe(Fingerprint::make($b));
})->with([
    'scalars' => [[1, 2], [2, 1]],
    'nested lists' => [[[1], [2]], [[2], [1]]],
    'list of maps' => [[['id' => 1], ['id' => 2]], [['id' => 2], ['id' => 1]]],
]);

it('preserves types', function () {
    $values = [1, '1', 1.0, true, 0, '0', 0.0, false, '', [], null];
    $hashes = array_map(fn ($value) => Fingerprint::make($value), $values);

    expect(array_unique($hashes))->toHaveCount(count($values));
});

it('treats null differently from a missing key', function () {
    expect(Fingerprint::make(['x' => null]))->not->toBe(Fingerprint::make([]))
        ->and(Fingerprint::make(['a' => ['x' => null]]))->not->toBe(Fingerprint::make(['a' => []]));
});

it('keeps list and map shapes apart', function () {
    expect(Fingerprint::make([0 => 'a', 1 => 'b']))->not->toBe(Fingerprint::make([1 => 'b', 0 => 'a']))
        ->and(Fingerprint::make([[]]))->not->toBe(Fingerprint::make([[[]]]));
});

it('has no boundary collisions between strings', function () {
    expect(Fingerprint::make(['ab', 'c']))->not->toBe(Fingerprint::make(['a', 'bc']))
        ->and(Fingerprint::make(['a' => 'bc']))->not->toBe(Fingerprint::make(['ab' => 'c']))
        ->and(Fingerprint::make('l1[n]'))->not->toBe(Fingerprint::make([null]));
});
