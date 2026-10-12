<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Fingerprint;

$secret = 'sk_live_do_not_leak_0123456789';

it('keeps the secret out of var_dump() and print_r()', function () use ($secret) {
    $builder = Fingerprint::for(['a' => 1])->hmac($secret)->namespace('n');

    ob_start();
    var_dump($builder);
    $dump = (string) ob_get_clean();

    expect($dump)->not->toContain($secret)
        ->and(print_r($builder, true))->not->toContain($secret)
        ->and($builder->__debugInfo())->toBe(['algorithm' => 'sha256', 'encoding' => 'hex', 'hmac' => true, 'namespace' => 'n']);
});

it('refuses to serialize a builder', function () use ($secret) {
    serialize(Fingerprint::for(1)->hmac($secret));
})->throws(LogicException::class, 'A fingerprint builder cannot be serialized because it may hold an HMAC secret.');

it('keeps the secret out of exception messages', function () use ($secret) {
    $messages = [];

    foreach ([
        fn () => Fingerprint::for(NAN)->hmac($secret)->make(),
        fn () => Fingerprint::for(1)->hmac($secret)->algorithm('crc32b'),
        fn () => Fingerprint::for(1)->hmac($secret)->algorithm('nope'),
        fn () => Fingerprint::for([1])->hmac($secret)->ignoreWhen(fn () => 1)->make(),
    ] as $failing) {
        try {
            $failing();
        } catch (Throwable $exception) {
            $messages[] = $exception->getMessage();
        }
    }

    expect($messages)->toHaveCount(4)
        ->and(implode("\n", $messages))->not->toContain($secret);
});
