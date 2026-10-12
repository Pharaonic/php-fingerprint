<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Fingerprint;
use Pharaonic\Fingerprint\FingerprintBuilder;

it('starts a builder for a value', function () {
    expect(Fingerprint::for(['a' => 1]))->toBeInstanceOf(FingerprintBuilder::class)
        ->and(Fingerprint::for(['a' => 1])->make())->toBe(Fingerprint::make(['a' => 1]));
});

it('rebinds the configuration to another value without changing the original', function () {
    $first = Fingerprint::for('first');
    $second = $first->for('second');

    expect($second)->not->toBe($first)
        ->and($first->make())->toBe(Fingerprint::make('first'))
        ->and($second->make())->toBe(Fingerprint::make('second'));
});
