<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Fingerprint;

$value = ['a' => 1];

it('verifies a fingerprint', function () use ($value) {
    $hash = Fingerprint::make($value);

    expect(Fingerprint::verify($value, $hash))->toBeTrue()
        ->and(Fingerprint::verify(['a' => 2], $hash))->toBeFalse()
        ->and(Fingerprint::for($value)->verify($hash))->toBeTrue();
});

it('compares hex case-insensitively', function () use ($value) {
    expect(Fingerprint::verify($value, strtoupper(Fingerprint::make($value))))->toBeTrue();
});

it('compares other encodings exactly', function () use ($value) {
    $builder = Fingerprint::for($value)->base64();
    $hash = $builder->make();

    expect($builder->verify($hash))->toBeTrue()
        ->and($builder->verify(strtolower($hash)))->toBeFalse()
        ->and($builder->binary()->verify($builder->binary()->make()))->toBeTrue();
});

it('rejects a fingerprint of the wrong length or encoding', function () use ($value) {
    $hash = Fingerprint::make($value);

    expect(Fingerprint::verify($value, substr($hash, 1)))->toBeFalse()
        ->and(Fingerprint::verify($value, $hash . '0'))->toBeFalse()
        ->and(Fingerprint::verify($value, ''))->toBeFalse()
        ->and(Fingerprint::for($value)->base64()->verify($hash))->toBeFalse();
});

it('verifies with the configured algorithm, secret and namespace', function () use ($value) {
    $builder = Fingerprint::for($value)->algorithm('sha512')->hmac('key')->namespace('n');
    $hash = $builder->make();

    expect($builder->verify($hash))->toBeTrue()
        ->and($builder->hmac('other')->verify($hash))->toBeFalse()
        ->and($builder->namespace('m')->verify($hash))->toBeFalse()
        ->and(Fingerprint::for($value)->algorithm('sha512')->verify($hash))->toBeFalse();
});
