<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Fingerprint;

$order = [
    'id' => 7,
    'updated_at' => '2026-10-12',
    'customer' => ['name' => 'Raggi', 'email' => 'r@example.com', 'seen_at' => 'x'],
    'items' => [
        ['sku' => 'A', 'price' => 10, 'updated_at' => 'p'],
        ['sku' => 'B', 'price' => 20, 'updated_at' => 'q'],
    ],
];

it('ignores one path or several', function () use ($order) {
    $without = $order;
    unset($without['updated_at'], $without['customer']['seen_at']);

    expect(Fingerprint::for($order)->ignore('updated_at')->ignore('customer.seen_at')->make())
        ->toBe(Fingerprint::make($without))
        ->toBe(Fingerprint::for($order)->ignore(['updated_at', 'customer.seen_at'])->make());
});

it('ignores every match of a wildcard path', function () use ($order) {
    $changed = $order;
    $changed['items'][0]['updated_at'] = 'changed';
    $changed['items'][1]['updated_at'] = 'changed';

    expect(Fingerprint::for($changed)->ignore('items.*.updated_at')->make())
        ->toBe(Fingerprint::for($order)->ignore('items.*.updated_at')->make())
        ->and(Fingerprint::make($changed))->not->toBe(Fingerprint::make($order));
});

it('treats a path that matches nothing as a no-op', function () use ($order) {
    expect(Fingerprint::for($order)->ignore('missing.path')->make())->toBe(Fingerprint::make($order))
        ->and(Fingerprint::for($order)->ignore('id.deeper')->make())->toBe(Fingerprint::make($order));
});

it('refers to input indexes when a list loses elements', function () {
    expect(Fingerprint::for(['a', 'b', 'c'])->ignore(['0', '2'])->make())->toBe(Fingerprint::make(['b']));
});

it('keeps only the selected paths whatever their order', function () use ($order) {
    $selected = ['id' => 7, 'customer' => ['email' => 'r@example.com'], 'items' => [['sku' => 'A'], ['sku' => 'B']]];

    expect(Fingerprint::for($order)->only(['id', 'customer.email', 'items.*.sku'])->make())
        ->toBe(Fingerprint::for($order)->only(['items.*.sku', 'id', 'customer.email'])->make())
        ->toBe(Fingerprint::make($selected));
});

it('keeps everything under a selected path', function () use ($order) {
    expect(Fingerprint::for($order)->only(['customer'])->make())
        ->toBe(Fingerprint::make(['customer' => $order['customer']]));
});

it('adds paths when only() is called again', function () use ($order) {
    expect(Fingerprint::for($order)->only(['id'])->only(['updated_at'])->make())
        ->toBe(Fingerprint::for($order)->only(['id', 'updated_at'])->make());
});

it('lets ignore win over only', function () use ($order) {
    expect(Fingerprint::for($order)->only(['customer'])->ignore('customer.seen_at')->make())
        ->toBe(Fingerprint::make(['customer' => ['name' => 'Raggi', 'email' => 'r@example.com']]));
});

it('keeps the root container when only() selects nothing', function () {
    expect(Fingerprint::for(['a' => 1])->only(['b'])->make())->toBe(hash('sha256', 'fp1;nm0{}'))
        ->and(Fingerprint::for([1, 2])->only(['5'])->make())->toBe(hash('sha256', 'fp1;nl0[]'));
});

it('does not filter a scalar root', function () {
    expect(Fingerprint::for('x')->only(['a'])->ignore('a')->make())->toBe(Fingerprint::make('x'));
});

it('ignores values chosen by a callback with their escaped path', function () {
    $seen = [];

    $hash = Fingerprint::for(['a.b' => 1, 'keep' => ['x' => 2, 'secret' => 3]])
        ->ignoreWhen(function (string $path, mixed $value) use (&$seen) {
            $seen[$path] = $value;

            return str_ends_with($path, 'secret');
        })
        ->make();

    expect($hash)->toBe(Fingerprint::make(['a.b' => 1, 'keep' => ['x' => 2]]))
        ->and(array_keys($seen))->toBe(['a\.b', 'keep', 'keep.x', 'keep.secret']);
});

it('passes the value before value rules to ignoreWhen()', function () {
    $hash = Fingerprint::for(['a' => ' x ', 'b' => 1])
        ->ignoreWhen(fn(string $path, mixed $value) => $value === ' x ')
        ->at('a')->transform(fn() => 'changed')
        ->make();

    expect($hash)->toBe(Fingerprint::make(['b' => 1]));
});

it('rejects an ignoreWhen() callback that does not return a boolean', function () {
    Fingerprint::for(['a' => 1])->ignoreWhen(fn() => 1)->make();
})->throws(ConfigurationException::class, 'An ignoreWhen() callback must return a boolean, int returned at path "a".');

it('ignores nulls, empty strings and empty arrays only when asked', function () {
    $value = ['n' => null, 's' => '', 'a' => [], 'm' => ['x' => null], 'k' => 0];

    expect(Fingerprint::make($value))->not->toBe(Fingerprint::make(['k' => 0]))
        ->and(Fingerprint::for($value)->ignoreNulls()->make())
        ->toBe(Fingerprint::for(['s' => '', 'a' => [], 'm' => ['x' => 1], 'k' => 0])->ignore('m.x')->make())
        ->and(Fingerprint::for($value)->ignoreEmptyStrings()->make())
        ->toBe(Fingerprint::make(['n' => null, 'a' => [], 'm' => ['x' => null], 'k' => 0]))
        ->and(Fingerprint::for($value)->ignoreNulls()->ignoreEmptyStrings()->ignoreEmptyArrays()->make())
        ->toBe(Fingerprint::make(['k' => 0]));
});

$invalid = [
    'empty ignore path' => fn($b) => $b->ignore(''),
    'empty segment' => fn($b) => $b->ignore('a..b'),
    'non-string path' => fn($b) => $b->ignore([1]),
    'empty only' => fn($b) => $b->only([]),
    'non-string only path' => fn($b) => $b->only([null]),
    'invalid at path' => fn($b) => $b->at('a.'),
];

it('rejects invalid paths and path lists', function (string $case) use ($invalid) {
    $invalid[$case](Fingerprint::for([]));
})->throws(ConfigurationException::class)->with(array_keys($invalid));
