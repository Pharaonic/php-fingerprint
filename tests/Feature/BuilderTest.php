<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Fingerprint;
use Pharaonic\Fingerprint\FingerprintBuilder;

it('starts a builder for a value', function () {
    expect(Fingerprint::for(['a' => 1]))->toBeInstanceOf(FingerprintBuilder::class)
        ->and(Fingerprint::for(['a' => 1])->make())->toBe(Fingerprint::make(['a' => 1]));
});

it('rebinds the configuration to another value without changing the original', function () {
    $first = Fingerprint::for(['a' => 1, 'b' => 2])->ignore('b');
    $second = $first->for(['a' => 1, 'b' => 3]);

    expect($second)->not->toBe($first)
        ->and($second->make())->toBe($first->make())
        ->and($first->for(['a' => 2])->make())->not->toBe($first->make());
});

$configurations = [
    'ignore' => fn ($b) => $b->ignore('a'),
    'only' => fn ($b) => $b->only(['a']),
    'ignoreWhen' => fn ($b) => $b->ignoreWhen(fn ($path) => $path === 'a'),
    'ignoreNulls' => fn ($b) => $b->ignoreNulls(),
    'ignoreEmptyStrings' => fn ($b) => $b->ignoreEmptyStrings(),
    'ignoreEmptyArrays' => fn ($b) => $b->ignoreEmptyArrays(),
    'transform' => fn ($b) => $b->at('a')->transform(fn () => 'y'),
];

it('never changes the builder a method is called on', function (string $method) use ($configurations) {
    $value = ['a' => ' x ', 'b' => null, 'c' => '', 'd' => [], 'e' => ['f' => 1]];
    $base = Fingerprint::for($value);
    $before = $base->make();

    $changed = $configurations[$method]($base);

    expect($changed)->not->toBe($base)
        ->and($changed->make())->not->toBe($before)
        ->and($base->make())->toBe($before);
})->with(array_keys($configurations));

it('does not share rules between builders derived from the same base', function () {
    $base = Fingerprint::for(['a' => 1, 'b' => 2, 'c' => 3]);
    $withoutA = $base->ignore('a');
    $withoutB = $base->ignore('b');

    expect($withoutA->make())->toBe(Fingerprint::make(['b' => 2, 'c' => 3]))
        ->and($withoutB->make())->toBe(Fingerprint::make(['a' => 1, 'c' => 3]));
});

it('does not apply a scope to the builder it came from', function () {
    $base = Fingerprint::for(['a' => 1, 'b' => 2]);
    $scoped = $base->at('a');

    expect($base->transform(fn () => 0)->make())->toBe(Fingerprint::make(0))
        ->and($scoped->transform(fn () => 0)->make())->toBe(Fingerprint::make(['a' => 0, 'b' => 2]));
});
