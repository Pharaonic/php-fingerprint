<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Fingerprint;

it('rounds floats so close values match', function () {
    expect(Fingerprint::for(0.1 + 0.2)->precision(2)->make())->toBe(Fingerprint::make(0.3))
        ->and(Fingerprint::for(['p' => 9.999])->at('p')->precision(0)->make())->toBe(Fingerprint::make(['p' => 10.0]));
});

it('rounds from the exact binary value, the same on every PHP version', function () {
    expect(Fingerprint::for(0.285)->precision(2)->make())->toBe(Fingerprint::make(0.28))
        ->and(Fingerprint::for(1.005)->precision(2)->make())->toBe(Fingerprint::make(1.0));
});

it('leaves integers, strings and infinities alone when rounding', function () {
    expect(Fingerprint::for([1, '1.234', INF, -INF])->precision(1)->make())->toBe(Fingerprint::make([1, '1.234', INF, -INF]));
});

it('folds a negative zero produced by rounding', function () {
    expect(Fingerprint::for(-0.001)->precision(2)->make())->toBe(Fingerprint::make(0.0));
});

it('rejects precision outside 0 to 53 digits', function (int $digits) {
    (void) Fingerprint::for(1.0)->precision($digits);
})->throws(ConfigurationException::class)->with([-1, 54]);

it('converts plain decimal strings into numbers', function (string $string, mixed $number) {
    expect(Fingerprint::for($string)->normalizeNumericStrings()->make())->toBe(Fingerprint::make($number));
})->with([
    'int' => ['42', 42],
    'negative int' => ['-42', -42],
    'zero' => ['0', 0],
    'decimal' => ['1.50', 1.5],
    'exponent' => ['2.5e-3', 0.0025],
    'int max' => ['9223372036854775807', PHP_INT_MAX],
    'int overflow' => ['9223372036854775808', 9223372036854775808.0],
]);

it('keeps strings that are not plain decimals', function (string $string) {
    expect(Fingerprint::for($string)->normalizeNumericStrings()->make())->toBe(Fingerprint::make($string));
})->with([' 1', '1 ', '+1', '01', '.5', '1.', '0x1A', '1_000', 'abc', '', 'INF', 'NAN']);

it('keeps integer and float types apart after conversion', function () {
    expect(Fingerprint::for('1.0')->normalizeNumericStrings()->make())->not->toBe(Fingerprint::make(1));
});

it('maps exactly eight values to booleans', function () {
    expect(Fingerprint::for(['1', 'true', 0, 'false', 'TRUE', 'yes', 2, null])->normalizeBooleans()->make())
        ->toBe(Fingerprint::make([true, true, false, false, 'TRUE', 'yes', 2, null]));
});

it('converts numeric strings before booleans', function () {
    expect(Fingerprint::for('1')->normalizeBooleans()->normalizeNumericStrings()->make())->toBe(Fingerprint::make(true));
});
