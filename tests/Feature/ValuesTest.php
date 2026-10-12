<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Exceptions\FingerprintException;
use Pharaonic\Fingerprint\Exceptions\MaximumDepthExceededException;
use Pharaonic\Fingerprint\Exceptions\UnsupportedValueException;
use Pharaonic\Fingerprint\Fingerprint;

it('returns a lowercase SHA-256 hex digest', function () {
    expect(Fingerprint::make('x'))->toMatch('/^[0-9a-f]{64}$/');
});

it('folds negative zero into zero', function () {
    expect(Fingerprint::make(-0.0))->toBe(Fingerprint::make(0.0));
});

it('tells floats that only differ in the last bit apart', function () {
    expect(Fingerprint::make(0.1 + 0.2))->not->toBe(Fingerprint::make(0.3));
});

it('rejects NAN', function () {
    Fingerprint::make(['a' => [1, NAN]]);
})->throws(UnsupportedValueException::class, 'NAN cannot be fingerprinted because it is not equal to itself at path "a.1".');

it('rejects closures', function () {
    Fingerprint::make(['fn' => fn () => 1]);
})->throws(UnsupportedValueException::class, 'A closure cannot be fingerprinted at path "fn".');

it('rejects resources', function () {
    $handle = fopen('php://memory', 'r');

    try {
        Fingerprint::make([$handle]);
    } finally {
        fclose($handle);
    }
})->throws(UnsupportedValueException::class, 'A stream resource cannot be fingerprinted at path "0".');

it('rejects closed resources', function () {
    $handle = fopen('php://memory', 'r');
    fclose($handle);

    Fingerprint::make($handle);
})->throws(UnsupportedValueException::class, 'A value of type resource (closed) cannot be fingerprinted at the root.');

it('rejects objects without a supported conversion', function () {
    Fingerprint::make(new DateTimeZone('UTC'));
})->throws(UnsupportedValueException::class, 'An object of class DateTimeZone cannot be fingerprinted at the root.');

it('escapes dots, stars and backslashes in error paths', function () {
    Fingerprint::make(['a.b' => ['*' => ['c\\d' => NAN]]]);
})->throws(UnsupportedValueException::class, 'at path "a\.b.\*.c\\\\d"');

it('makes every exception catchable as a FingerprintException', function () {
    try {
        Fingerprint::make(NAN);
    } catch (Throwable $exception) {
    }

    expect($exception ?? null)->toBeInstanceOf(FingerprintException::class);
});

it('accepts nesting up to the maximum depth', function () {
    $value = 'leaf';

    for ($i = 0; $i < 512; $i++) {
        $value = [$value];
    }

    expect(Fingerprint::make($value))->toBeString();
});

it('rejects nesting deeper than the maximum depth', function () {
    $value = 'leaf';

    for ($i = 0; $i < 513; $i++) {
        $value = [$value];
    }

    Fingerprint::make($value);
})->throws(MaximumDepthExceededException::class, 'nested deeper than the maximum depth of 512');

it('stops on self-referencing arrays instead of recursing forever', function () {
    $value = ['a' => 1];
    $value['self'] = &$value;

    expect(fn () => Fingerprint::make($value))->toThrow(MaximumDepthExceededException::class);
});

it('handles large strings', function () {
    $string = str_repeat('x', 1024 * 1024);

    expect(Fingerprint::make($string))->toBe(hash('sha256', 'fp1;ns1048576:' . $string));
});
