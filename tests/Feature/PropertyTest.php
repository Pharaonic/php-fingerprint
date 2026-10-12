<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Fingerprint;
use Pharaonic\Fingerprint\Tests\Support\Gen;

dataset('seeds', [11, 23, 37, 41, 53]);

it('never changes when map keys are reordered', function (int $seed) {
    mt_srand($seed);

    for ($i = 0; $i < 200; $i++) {
        $value = Gen::map(4);

        expect(Fingerprint::make(Gen::shuffleMaps($value)))->toBe(Fingerprint::make($value));
    }
})->with('seeds');

it('changes when a list is reordered', function (int $seed) {
    mt_srand($seed);

    for ($i = 0; $i < 200; $i++) {
        $list = Gen::list(3);
        $shuffled = $list;
        shuffle($shuffled);

        if (canonical($shuffled) === canonical($list)) {
            continue; // Same order, or equal elements swapped.
        }

        expect(Fingerprint::make($shuffled))->not->toBe(Fingerprint::make($list));
    }
})->with('seeds');

it('is repeatable on a reused builder', function (int $seed) {
    mt_srand($seed);

    for ($i = 0; $i < 200; $i++) {
        $builder = Fingerprint::for(Gen::value());

        expect($builder->make())->toBe($builder->make());
    }
})->with('seeds');

it('never collides when strings are split differently', function (int $seed) {
    mt_srand($seed);

    for ($i = 0; $i < 100; $i++) {
        $string = Gen::string(12) . 'xy';
        $seen = [];

        for ($at = 0; $at <= strlen($string); $at++) {
            $pair = [substr($string, 0, $at), substr($string, $at)];

            $seen[Fingerprint::make($pair)] = true;
            $seen[Fingerprint::make([$pair])] = true;
            $seen[Fingerprint::make([$pair[0] => $pair[1]])] = true;
        }

        // Every split gives a distinct list and nested list; the map form collapses
        // only when PHP casts two different keys to the same one, which never happens here.
        expect($seen)->toHaveCount(3 * (strlen($string) + 1));
    }
})->with('seeds');

it('gives identical documents identical digests', function (int $seed) {
    mt_srand($seed);

    for ($i = 0; $i < 50; $i++) {
        $value = Gen::value();

        expect(Fingerprint::make($value))->toBe(hash('sha256', canonical($value)));
    }
})->with('seeds');
