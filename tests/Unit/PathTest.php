<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Support\Path;

it('formats segments as a dot path', function (array $segments, string $path) {
    expect(Path::format($segments))->toBe($path);
})->with([
    'root' => [[], ''],
    'keys and indexes' => [['items', 0, 'id'], 'items.0.id'],
    'escaped characters' => [['a.b', '*', 'c\\d'], 'a\.b.\*.c\\\\d'],
    'empty key' => [['a', ''], 'a.'],
]);

it('parses dot paths into segments with null for wildcards', function (string $path, array $segments) {
    expect(Path::parse($path))->toBe($segments);
})->with([
    'single key' => ['a', ['a']],
    'nested' => ['user.email', ['user', 'email']],
    'wildcard' => ['items.*.price', ['items', null, 'price']],
    'index' => ['items.0', ['items', '0']],
    'escaped dot' => ['a\.b.c', ['a.b', 'c']],
    'escaped star' => ['\*', ['*']],
    'star inside a key is literal' => ['a*', ['a*']],
    'escaped backslash' => ['a\\\\b', ['a\\b']],
    'lone backslash is literal' => ['a\b', ['a\b']],
    'spaces are kept' => [' a ', [' a ']],
]);

it('rejects empty paths and empty segments', function (string $path) {
    Path::parse($path);
})->throws(ConfigurationException::class)->with(['', '.', 'a..b', '.a', 'a.', '*.']);

it('matches paths exactly, with wildcards for one segment', function (string $pattern, array $path, bool $matches) {
    expect(Path::matches(Path::parse($pattern), $path))->toBe($matches);
})->with([
    'exact' => ['a.b', ['a', 'b'], true],
    'int key against string segment' => ['items.0', ['items', 0], true],
    'wildcard key' => ['a.*', ['a', 'x'], true],
    'wildcard index' => ['*.id', [3, 'id'], true],
    'shorter path' => ['a.b', ['a'], false],
    'longer path' => ['a', ['a', 'b'], false],
    'other key' => ['a.b', ['a', 'c'], false],
]);

it('knows when a path leads to what a pattern matches', function (string $pattern, array $path, bool $leads) {
    expect(Path::leadsTo(Path::parse($pattern), $path))->toBe($leads);
})->with([
    'ancestor' => ['a.b.c', ['a'], true],
    'wildcard ancestor' => ['*.b', [7], true],
    'same length' => ['a.b', ['a', 'b'], false],
    'other branch' => ['a.b', ['x'], false],
]);
