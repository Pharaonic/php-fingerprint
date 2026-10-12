<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Support\Path;

it('formats segments as a dot path', function (array $segments, string $path) {
    expect(Path::format($segments))->toBe($path);
})->with([
    'root' => [[], ''],
    'keys and indexes' => [['items', 0, 'id'], 'items.0.id'],
    'escaped characters' => [['a.b', '*', 'c\\d'], 'a\.b.\*.c\\\\d'],
    'empty key' => [['a', ''], 'a.'],
]);
