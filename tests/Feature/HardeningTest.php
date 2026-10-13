<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Exceptions\FingerprintException;
use Pharaonic\Fingerprint\Exceptions\MaximumDepthExceededException;
use Pharaonic\Fingerprint\Fingerprint;

function nested(int $levels): array
{
    $value = ['leaf'];

    for ($i = 1; $i < $levels; $i++) {
        $value = [$value];
    }

    return $value;
}

it('accepts nesting up to a custom maximum depth', function () {
    expect(Fingerprint::for(nested(3))->maxDepth(3)->make())->toBe(Fingerprint::make(nested(3)));
});

it('rejects nesting deeper than a custom maximum depth', function () {
    Fingerprint::for(nested(4))->maxDepth(3)->make();
})->throws(MaximumDepthExceededException::class, 'nested deeper than the maximum depth of 3 at path "0.0.0"');

it('counts objects towards the depth', function () {
    $value = (object) ['a' => (object) ['b' => (object) ['c' => 1]]];

    expect(fn() => Fingerprint::for($value)->maxDepth(2)->make())->toThrow(MaximumDepthExceededException::class)
        ->and(Fingerprint::for($value)->maxDepth(3)->make())->toBeString();
});

it('allows deeper nesting than the default when asked', function () {
    expect(Fingerprint::for(nested(600))->maxDepth(600)->make())->toBeString()
        ->and(fn() => Fingerprint::make(nested(600)))->toThrow(MaximumDepthExceededException::class);
});

it('rejects a maximum depth below 1', function (int $depth) {
    Fingerprint::for([])->maxDepth($depth);
})->throws(ConfigurationException::class, 'maxDepth() must be at least 1')->with([0, -1]);

it('makes every package exception a FingerprintException', function () {
    $classes = array_map(
        fn(string $file) => 'Pharaonic\\Fingerprint\\Exceptions\\' . basename($file, '.php'),
        glob(dirname(__DIR__, 2) . '/src/Exceptions/*.php') ?: [],
    );

    expect($classes)->toHaveCount(8);

    foreach ($classes as $class) {
        expect(is_subclass_of($class, Throwable::class))->toBeTrue()
            ->and($class === FingerprintException::class || is_subclass_of($class, FingerprintException::class))->toBeTrue();
    }
});
