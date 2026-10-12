<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Fingerprint;

$value = ['price' => 0.1 + 0.2, 'big' => 1e22, 'name' => 'İstanbul', 'n' => -0.0];

it('ignores precision ini settings', function () use ($value) {
    $expected = Fingerprint::make($value);
    $precision = ini_get('precision');
    $serialize = ini_get('serialize_precision');

    try {
        ini_set('precision', '3');
        ini_set('serialize_precision', '5');

        expect(Fingerprint::make($value))->toBe($expected);
    } finally {
        ini_set('precision', (string) $precision);
        ini_set('serialize_precision', (string) $serialize);
    }
});

it('ignores the locale', function () use ($value) {
    $expected = Fingerprint::make($value);
    $locale = setlocale(LC_ALL, '0');

    $applied = 0;

    try {
        foreach (['tr_TR.UTF-8', 'de_DE.UTF-8', 'ar_EG.UTF-8'] as $candidate) {
            if (setlocale(LC_ALL, $candidate) === false) {
                continue; // Not installed on this machine.
            }

            $applied++;

            expect(Fingerprint::make($value))->toBe($expected);
        }
    } finally {
        setlocale(LC_ALL, (string) $locale);
    }

    if ($applied === 0) {
        $this->markTestSkipped('None of the test locales is installed.');
    }
});
