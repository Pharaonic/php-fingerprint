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

it('gives identical documents identical digests with every algorithm', function (int $seed) {
    mt_srand($seed);

    for ($i = 0; $i < 20; $i++) {
        $value = Gen::value();
        $copy = Gen::shuffleMaps($value);
        $document = canonical($value);

        foreach (hash_algos() as $algorithm) {
            expect(Fingerprint::for($copy)->algorithm($algorithm)->make())->toBe(hash($algorithm, $document));
        }
    }
})->with('seeds');

it('never depends on the order of only() paths', function (int $seed) {
    mt_srand($seed);

    for ($i = 0; $i < 100; $i++) {
        $value = Gen::map(3);
        $paths = array_map(fn ($key) => addcslashes((string) $key, '.*\\'), array_keys($value));
        $paths = array_values(array_filter($paths, fn ($path) => $path !== ''));
        $paths[] = '*.*';
        $shuffled = $paths;
        shuffle($shuffled);

        expect(Fingerprint::for($value)->only($shuffled)->make())->toBe(Fingerprint::for($value)->only($paths)->make());
    }
})->with('seeds');

it('never changes when an unordered list is shuffled', function (int $seed) {
    mt_srand($seed);

    for ($i = 0; $i < 200; $i++) {
        $list = Gen::list(3);
        $shuffled = $list;
        shuffle($shuffled);

        expect(Fingerprint::for($shuffled)->unordered()->make())->toBe(Fingerprint::for($list)->unordered()->make());
    }
})->with('seeds');

it('sorts shuffled items with unique keys into one result', function (int $seed) {
    mt_srand($seed);

    for ($i = 0; $i < 100; $i++) {
        $items = [];

        foreach (array_unique(array_map(fn () => mt_rand(-50, 50), range(0, mt_rand(0, 8)))) as $id) {
            $items[] = ['id' => $id, 'payload' => Gen::value(2)];
        }

        $shuffled = $items;
        shuffle($shuffled);

        expect(Fingerprint::for($shuffled)->sortBy('id')->make())->toBe(Fingerprint::for($items)->sortBy('id')->make());
    }
})->with('seeds');

it('gives one fingerprint to an instant in any timezone', function (int $seed) {
    mt_srand($seed);
    $zones = DateTimeZone::listIdentifiers();

    for ($i = 0; $i < 50; $i++) {
        $instant = new DateTimeImmutable('@' . mt_rand(0, 4102444800) . '.' . str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT));
        $expected = Fingerprint::make($instant);

        for ($j = 0; $j < 5; $j++) {
            $zoned = $instant->setTimezone(new DateTimeZone($zones[mt_rand(0, count($zones) - 1)]));

            expect(Fingerprint::make($zoned))->toBe($expected);
        }
    }
})->with('seeds');
