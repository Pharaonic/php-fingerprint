<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Fingerprint;
use Pharaonic\Fingerprint\Support\Canonicalizer;

it('treats only arrays keyed 0 to n-1 in order as lists', function (array $value, bool $isList) {
    expect(str_starts_with(Fingerprint::for($value)->result()->canonical(), 'fp1;nl'))->toBe($isList);
})->with([
    'empty' => [[], true],
    'sequential' => [['a', 'b'], true],
    'reordered keys' => [[1 => 'b', 0 => 'a'], false],
    'gap' => [[0 => 'a', 2 => 'c'], false],
    'starts at one' => [[1 => 'a'], false],
    'string key' => [['a' => 1], false],
]);

it('puts the namespace in the document header', function () {
    expect((new Canonicalizer('orders'))->canonicalize(1))->toBe('fp1;s6:ordersi1;')
        ->and((new Canonicalizer(''))->canonicalize(1))->toBe('fp1;s0:i1;')
        ->and((new Canonicalizer())->canonicalize(1))->toBe('fp1;ni1;');
});
