<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Fingerprint;

it('transforms only the values at the scoped path', function () {
    $hash = Fingerprint::for(['users' => [['email' => ' A@X.IO ', 'name' => ' A ']]])
        ->at('users.*.email')->transform(fn(string $email) => strtolower(trim($email)))
        ->make();

    expect($hash)->toBe(Fingerprint::make(['users' => [['email' => 'a@x.io', 'name' => ' A ']]]));
});

it('applies rules declared before any at() to every value, the root included', function () {
    $paths = [];

    (void) Fingerprint::for(['a' => [1]])
        ->transform(function (mixed $value, string $path) use (&$paths) {
            $paths[] = $path;

            return $value;
        })
        ->make();

    expect($paths)->toBe(['', 'a', 'a.0']);
});

it('lets a path rule override a global rule of the same kind', function () {
    $hash = Fingerprint::for(['a' => 1, 'b' => 2])
        ->transform(fn(mixed $value) => is_int($value) ? $value * 10 : $value)
        ->at('b')->transform(fn() => 'b')
        ->make();

    expect($hash)->toBe(Fingerprint::make(['a' => 10, 'b' => 'b']));
});

it('lets the last declared rule of a kind win for a path', function () {
    $hash = Fingerprint::for(['a' => 1])
        ->at('a')->transform(fn() => 'first')
        ->at('*')->transform(fn() => 'second')
        ->make();

    expect($hash)->toBe(Fingerprint::make(['a' => 'second']));
});

it('keeps a scope until the next at()', function () {
    $hash = Fingerprint::for(['a' => 1, 'b' => 2, 'c' => 3])
        ->at('a')->transform(fn() => 'x')
        ->ignore('c')
        ->at('b')->transform(fn() => 'y')
        ->make();

    expect($hash)->toBe(Fingerprint::make(['a' => 'x', 'b' => 'y']));
});

it('normalizes what a transform returns, including arrays', function () {
    $hash = Fingerprint::for(['money' => '10 EGP'])
        ->at('money')->transform(fn(string $v) => ['currency' => 'EGP', 'amount' => 10])
        ->ignore('money.currency')
        ->make();

    expect($hash)->toBe(Fingerprint::make(['money' => ['amount' => 10]]));
});

it('passes the escaped path to a transform', function () {
    $seen = null;

    (void) Fingerprint::for(['a.b' => ['*' => 1]])
        ->at('a\.b.\*')->transform(function (mixed $value, string $path) use (&$seen) {
            $seen = $path;

            return $value;
        })
        ->make();

    expect($seen)->toBe('a\.b.\*');
});
