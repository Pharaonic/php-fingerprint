<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Fingerprint;

$value = ['id' => 7, 'tags' => ['a', 'b']];
$document = 'fp1;nm2{s2:idi7;s4:tagsl2[s1:as1:b]}';

it('uses SHA-256 hex by default', function () use ($value, $document) {
    expect(Fingerprint::make($value))->toBe(hash('sha256', $document));
});

it('hashes with any algorithm hash() supports', function (string $algorithm) use ($value, $document) {
    expect(Fingerprint::for($value)->algorithm($algorithm)->make())->toBe(hash($algorithm, $document));
})->with(hash_algos());

it('signs with HMAC', function (string $algorithm) use ($value, $document) {
    expect(Fingerprint::for($value)->algorithm($algorithm)->hmac('test-key')->make())
        ->toBe(hash_hmac($algorithm, $document, 'test-key'))
        ->and(Fingerprint::for($value)->hmac('test-key')->algorithm($algorithm)->make())
        ->toBe(hash_hmac($algorithm, $document, 'test-key'));
})->with(['sha256', 'sha512', 'sha3-256', 'md5']);

it('encodes the digest', function () use ($value, $document) {
    $raw = hash('sha256', $document, true);

    expect(Fingerprint::for($value)->hex()->make())->toBe(bin2hex($raw))
        ->and(Fingerprint::for($value)->binary()->make())->toBe($raw)
        ->and(Fingerprint::for($value)->base64()->make())->toBe(base64_encode($raw))
        ->and(Fingerprint::for($value)->base64Url()->make())->toBe(rtrim(strtr(base64_encode($raw), '+/', '-_'), '='));
});

it('produces URL-safe base64 without padding', function () {
    for ($i = 0; $i < 50; $i++) {
        expect(Fingerprint::for($i)->algorithm('md5')->base64Url()->make())->not->toMatch('/[+\/=]/');
    }
});

it('lets the last encoding win', function () use ($value) {
    expect(Fingerprint::for($value)->binary()->hex()->make())->toBe(Fingerprint::make($value));
});

it('separates namespaces', function () use ($value) {
    $invoice = Fingerprint::for($value)->namespace('invoice')->make();

    expect($invoice)->not->toBe(Fingerprint::for($value)->namespace('order')->make())
        ->and($invoice)->not->toBe(Fingerprint::make($value))
        ->and($invoice)->toBe(Fingerprint::for($value)->namespace('invoice')->make());
});

$invalid = [
    'unknown algorithm' => fn($b) => $b->algorithm('sha257'),
    'HMAC with xxh128, reported when hashing' => fn($b) => $b->algorithm('xxh128')->hmac('key')->make(),
    'HMAC with a checksum, reported when hashing' => fn($b) => $b->algorithm('crc32b')->hmac('key')->make(),
    'checksum after HMAC' => fn($b) => $b->hmac('key')->algorithm('fnv1a64'),
    'empty secret' => fn($b) => $b->hmac(''),
    'empty namespace' => fn($b) => $b->namespace(''),
];

it('rejects invalid hashing options', function (string $case) use ($invalid) {
    $invalid[$case](Fingerprint::for(1));
})->throws(ConfigurationException::class)->with(array_keys($invalid));

it('names the algorithm and PHP version when an algorithm is missing', function () {
    Fingerprint::for(1)->algorithm('nope');
})->throws(ConfigurationException::class, 'The hash algorithm "nope" is not available on PHP ' . PHP_VERSION . '.');
