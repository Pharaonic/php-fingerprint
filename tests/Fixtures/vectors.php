<?php

declare(strict_types=1);

/*
 * Golden vectors for canonical format v1.
 *
 * Every branch (8.0.x … 8.5.x) must reproduce these bytes exactly. The expected documents are
 * written by hand from the format specification, never captured from the implementation.
 * Changing an existing entry is a canonical-format change; later branches may only add entries.
 *
 * Entry: [name, fn () => input, ?fn (FingerprintBuilder) => FingerprintBuilder, expected document]
 */

return [
    // Scalars
    ['null', fn () => null, null, 'fp1;nn'],
    ['true', fn () => true, null, 'fp1;nb1'],
    ['false', fn () => false, null, 'fp1;nb0'],
    ['int zero', fn () => 0, null, 'fp1;ni0;'],
    ['int negative', fn () => -42, null, 'fp1;ni-42;'],
    ['int max', fn () => PHP_INT_MAX, null, 'fp1;ni9223372036854775807;'],
    ['int min', fn () => PHP_INT_MIN, null, 'fp1;ni-9223372036854775808;'],
    ['float zero', fn () => 0.0, null, 'fp1;nd0000000000000000'],
    ['float negative zero', fn () => -0.0, null, 'fp1;nd0000000000000000'],
    ['float one', fn () => 1.0, null, 'fp1;nd3ff0000000000000'],
    ['float 0.1', fn () => 0.1, null, 'fp1;nd3fb999999999999a'],
    ['float -1.5', fn () => -1.5, null, 'fp1;ndbff8000000000000'],
    ['float 1e308', fn () => 1e308, null, 'fp1;nd7fe1ccf385ebc8a0'],
    ['float max', fn () => PHP_FLOAT_MAX, null, 'fp1;nd7fefffffffffffff'],
    ['float smallest subnormal', fn () => 5e-324, null, 'fp1;nd0000000000000001'],
    ['float INF', fn () => INF, null, 'fp1;nd7ff0000000000000'],
    ['float -INF', fn () => -INF, null, 'fp1;ndfff0000000000000'],
    ['string empty', fn () => '', null, 'fp1;ns0:'],
    ['string ascii', fn () => 'hello', null, 'fp1;ns5:hello'],
    ['string utf-8 counts bytes', fn () => 'héllo', null, 'fp1;ns6:héllo'],
    ['string with NUL byte', fn () => "a\0b", null, "fp1;ns3:a\0b"],
    ['string invalid utf-8', fn () => "\xff\xfe", null, "fp1;ns2:\xff\xfe"],
    ['numeric string stays a string', fn () => '1', null, 'fp1;ns1:1'],

    // Lists
    ['empty array is an empty list', fn () => [], null, 'fp1;nl0[]'],
    ['list', fn () => [1, 2], null, 'fp1;nl2[i1;i2;]'],
    ['list keeps order', fn () => [2, 1], null, 'fp1;nl2[i2;i1;]'],
    ['list of mixed types', fn () => [1, '1', 1.0, true, null], null, 'fp1;nl5[i1;s1:1d3ff0000000000000b1n]'],
    ['nested lists', fn () => [[1], [2, [3]]], null, 'fp1;nl2[l1[i1;]l2[i2;l1[i3;]]]'],
    ['list from string zero key', fn () => ['0' => 'x'], null, 'fp1;nl1[s1:x]'],

    // Maps
    ['map sorted by key', fn () => ['b' => 2, 'a' => 1], null, 'fp1;nm2{s1:ai1;s1:bi2;}'],
    ['map key tokens compare bytewise', fn () => ['aa' => 1, 'b' => 2], null, 'fp1;nm2{s1:bi2;s2:aai1;}'],
    ['map with int and string keys', fn () => [10 => 'x', 'a' => 'y', 2 => 'z'], null, 'fp1;nm3{i10;s1:xi2;s1:zs1:as1:y}'],
    ['sparse int keys are a map', fn () => [1 => 'a', 0 => 'b'], null, 'fp1;nm2{i0;s1:bi1;s1:a}'],
    ['int-like string key is cast by PHP', fn () => ['1' => 'x'], null, 'fp1;nm1{i1;s1:x}'],
    ['null value is kept', fn () => ['name' => null], null, 'fp1;nm1{s4:namen}'],
    [
        'nested maps and lists',
        fn () => ['z' => ['y' => 1, 'x' => [true, null]], 'a' => 0.5],
        null,
        'fp1;nm2{s1:ad3fe0000000000000s1:zm2{s1:xl2[b1n]s1:yi1;}}',
    ],
    [
        'list of maps',
        fn () => [['id' => 2, 'tag' => 'b'], ['tag' => 'a', 'id' => 1]],
        null,
        'fp1;nl2[m2{s2:idi2;s3:tags1:b}m2{s2:idi1;s3:tags1:a}]',
    ],
    // Filtering and path rules
    [
        'ignore a nested key',
        fn () => ['a' => 1, 'b' => ['c' => 2, 'd' => 3]],
        fn ($b) => $b->ignore('b.c'),
        'fp1;nm2{s1:ai1;s1:bm1{s1:di3;}}',
    ],
    [
        'ignore with a wildcard',
        fn () => ['items' => [['id' => 1, 'at' => 'x'], ['id' => 2, 'at' => 'y']]],
        fn ($b) => $b->ignore('items.*.at'),
        'fp1;nm1{s5:itemsl2[m1{s2:idi1;}m1{s2:idi2;}]}',
    ],
    ['ignore a list element re-indexes the list', fn () => [10, 20, 30], fn ($b) => $b->ignore('1'), 'fp1;nl2[i10;i30;]'],
    ['a map emptied by ignore stays a map', fn () => ['m' => ['x' => 1]], fn ($b) => $b->ignore('m.x'), 'fp1;nm1{s1:mm0{}}'],
    ['ignore an escaped dot', fn () => ['a.b' => 1, 'a' => ['b' => 2]], fn ($b) => $b->ignore('a\.b'), 'fp1;nm1{s1:am1{s1:bi2;}}'],
    [
        'only with a wildcard',
        fn () => ['id' => 1, 'name' => 'n', 'address' => ['city' => 'c', 'zip' => 'z'], 'x' => true],
        fn ($b) => $b->only(['id', 'address.*']),
        'fp1;nm2{s2:idi1;s7:addressm2{s3:zips1:zs4:citys1:c}}',
    ],
    [
        'only drops ancestors without a selected child',
        fn () => ['user' => ['name' => 'a'], 'id' => 1],
        fn ($b) => $b->only(['user.email', 'id']),
        'fp1;nm1{s2:idi1;}',
    ],
    [
        'transform at a path',
        fn () => ['email' => ' A@B.C ', 'name' => ' N '],
        fn ($b) => $b->at('email')->transform(fn ($v) => strtolower(trim($v))),
        'fp1;nm2{s4:names3: N s5:emails5:a@b.c}',
    ],
    [
        'ignore nulls and the arrays they empty',
        fn () => ['a' => null, 'b' => ['c' => null], 'd' => 1],
        fn ($b) => $b->ignoreNulls()->ignoreEmptyArrays(),
        'fp1;nm1{s1:di1;}',
    ],
];
