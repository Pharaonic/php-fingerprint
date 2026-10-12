<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Fingerprint;

$secret = 'test-secret-do-not-leak-0123456789';

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
        fn () => Fingerprint::for(1)->algorithm('crc32b')->hmac($secret)->make(),
        fn () => Fingerprint::for(1)->hmac($secret)->algorithm('nope'),
        fn () => Fingerprint::for([1])->hmac($secret)->ignoreWhen(fn () => 1)->make(),
    ] as $failing) {
        try {
            $failing();
        } catch (Throwable $exception) {
            $messages[] = $exception->getMessage();
        }
    }

    expect($messages)->toHaveCount(5)
        ->and(implode("\n", $messages))->not->toContain($secret);
});

it('keeps the secret out of exception traces, even with arguments recorded', function () use ($secret) {
    $ignoreArgs = ini_get('zend.exception_ignore_args');
    ini_set('zend.exception_ignore_args', '0');
    $traces = [];

    try {
        foreach ([
            fn () => Fingerprint::for(1)->algorithm('crc32b')->hmac($secret)->make(),
            fn () => Fingerprint::file(__FILE__)->algorithm('joaat')->hmac($secret)->verify('x'),
            fn () => Fingerprint::for(1)->hmac($secret)->algorithm('fnv1a64'),
            fn () => Fingerprint::for(NAN)->hmac($secret)->make(),
            fn () => Fingerprint::signature([])->fields(['missing'])->hmac($secret)->make(),
            fn () => Fingerprint::file('/missing/file')->hmac($secret)->make(),
        ] as $failing) {
            try {
                $failing();
            } catch (Throwable $exception) {
                // Only the package's frames: the test runner's frames hold this test's own closures.
                $frames = array_filter(
                    $exception->getTrace(),
                    fn (array $frame) => str_starts_with($frame['class'] ?? '', 'Pharaonic\\Fingerprint\\')
                        && ! str_starts_with($frame['class'] ?? '', 'Pharaonic\\Fingerprint\\Tests\\'),
                );

                expect($frames)->not->toBeEmpty();

                $traces[] = print_r($frames, true);
            }
        }
    } finally {
        ini_set('zend.exception_ignore_args', (string) $ignoreArgs);
    }

    expect($traces)->toHaveCount(6)
        ->and(implode("\n", $traces))->not->toContain($secret);
});
