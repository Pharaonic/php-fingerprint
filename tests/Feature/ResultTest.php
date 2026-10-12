<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Fingerprint;
use Pharaonic\Fingerprint\FingerprintResult;

it('describes a fingerprint', function () {
    $builder = Fingerprint::for(['b' => 2, 'a' => 1])->namespace('orders')->algorithm('sha512')->base64();
    $result = $builder->result();

    expect($result)->toBeInstanceOf(FingerprintResult::class)
        ->and($result->hash())->toBe($builder->make())
        ->and($result->algorithm())->toBe('sha512')
        ->and($result->encoding())->toBe('base64')
        ->and($result->hmac())->toBeFalse()
        ->and($result->namespace())->toBe('orders')
        ->and($result->version())->toBe('v1')
        ->and($result->canonical())->toBe('fp1;s6:ordersm2{s1:ai1;s1:bi2;}');
});

it('describes the defaults', function () {
    $result = Fingerprint::for(1)->result();

    expect($result->toArray())->toBe([
        'hash' => hash('sha256', 'fp1;ni1;'),
        'algorithm' => 'sha256',
        'encoding' => 'hex',
        'hmac' => false,
        'namespace' => null,
        'version' => 'v1',
        'canonical' => 'fp1;ni1;',
        'normalized' => 1,
    ]);
});

it('shows the normalized value as plain PHP data', function () {
    $value = [
        'user' => (object) ['email' => ' A@X.IO ', 'seen' => 'x'],
        'tags' => ['b', 'a'],
        'at' => new DateTimeImmutable('2026-10-12 10:00:00+02:00'),
        'zoned' => new DateTimeImmutable('2026-10-12 10:00:00+02:00'),
        'emptied' => ['x' => 1],
    ];

    $normalized = Fingerprint::for($value)
        ->ignore(['user.seen', 'emptied.x'])
        ->at('user.email')->trim()->lower()
        ->at('tags')->unordered()
        ->at('zoned')->keepTimezone()
        ->result()
        ->normalized();

    expect($normalized)->toBe([
        'user' => ['email' => 'a@x.io'],
        'tags' => ['a', 'b'],
        'at' => '2026-10-12T08:00:00.000000Z',
        'zoned' => '2026-10-12T10:00:00.000000 +02:00',
        'emptied' => [],
    ]);
});

it('shows where two fingerprints differ', function () {
    $a = Fingerprint::for(['id' => 1, 'total' => 10])->result();
    $b = Fingerprint::for(['id' => 1, 'total' => '10'])->result();

    expect($a->hash())->not->toBe($b->hash())
        ->and($a->canonical())->toBe('fp1;nm2{s2:idi1;s5:totali10;}')
        ->and($b->canonical())->toBe('fp1;nm2{s2:idi1;s5:totals2:10}');
});

it('never holds the HMAC secret', function () {
    $result = Fingerprint::for(['a' => 1])->hmac('result-secret-key')->result();

    expect($result->hmac())->toBeTrue()
        ->and($result->hash())->toBe(hash_hmac('sha256', 'fp1;nm1{s1:ai1;}', 'result-secret-key'))
        ->and(serialize($result))->not->toContain('result-secret-key')
        ->and(print_r($result, true))->not->toContain('result-secret-key');
});
