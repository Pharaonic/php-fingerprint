<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Tests\Fixtures\Enums\Priority;
use Pharaonic\Fingerprint\Tests\Fixtures\Enums\State;
use Pharaonic\Fingerprint\Tests\Fixtures\Enums\Status;
use Pharaonic\Fingerprint\Tests\Fixtures\Enums\Suit;

/*
 * Golden vectors for canonical format v1.
 *
 * Every branch (8.0.x … 8.5.x) must reproduce these bytes exactly; the enum entries start on 8.1.x. The expected documents are
 * written by hand from the format specification, never captured from the implementation.
 * Changing an existing entry is a canonical-format change; later branches may only add entries.
 *
 * Entry: [name, fn () => input, ?fn (FingerprintBuilder) => FingerprintBuilder, expected document]
 */

return [
    // Scalars
    ['null', fn() => null, null, 'fp1;nn'],
    ['true', fn() => true, null, 'fp1;nb1'],
    ['false', fn() => false, null, 'fp1;nb0'],
    ['int zero', fn() => 0, null, 'fp1;ni0;'],
    ['int negative', fn() => -42, null, 'fp1;ni-42;'],
    ['int max', fn() => PHP_INT_MAX, null, 'fp1;ni9223372036854775807;'],
    ['int min', fn() => PHP_INT_MIN, null, 'fp1;ni-9223372036854775808;'],
    ['float zero', fn() => 0.0, null, 'fp1;nd0000000000000000'],
    ['float negative zero', fn() => -0.0, null, 'fp1;nd0000000000000000'],
    ['float one', fn() => 1.0, null, 'fp1;nd3ff0000000000000'],
    ['float 0.1', fn() => 0.1, null, 'fp1;nd3fb999999999999a'],
    ['float -1.5', fn() => -1.5, null, 'fp1;ndbff8000000000000'],
    ['float 1e308', fn() => 1e308, null, 'fp1;nd7fe1ccf385ebc8a0'],
    ['float max', fn() => PHP_FLOAT_MAX, null, 'fp1;nd7fefffffffffffff'],
    ['float smallest subnormal', fn() => 5e-324, null, 'fp1;nd0000000000000001'],
    ['float INF', fn() => INF, null, 'fp1;nd7ff0000000000000'],
    ['float -INF', fn() => -INF, null, 'fp1;ndfff0000000000000'],
    ['string empty', fn() => '', null, 'fp1;ns0:'],
    ['string ascii', fn() => 'hello', null, 'fp1;ns5:hello'],
    ['string utf-8 counts bytes', fn() => 'héllo', null, 'fp1;ns6:héllo'],
    ['string with NUL byte', fn() => "a\0b", null, "fp1;ns3:a\0b"],
    ['string invalid utf-8', fn() => "\xff\xfe", null, "fp1;ns2:\xff\xfe"],
    ['numeric string stays a string', fn() => '1', null, 'fp1;ns1:1'],

    // Lists
    ['empty array is an empty list', fn() => [], null, 'fp1;nl0[]'],
    ['list', fn() => [1, 2], null, 'fp1;nl2[i1;i2;]'],
    ['list keeps order', fn() => [2, 1], null, 'fp1;nl2[i2;i1;]'],
    ['list of mixed types', fn() => [1, '1', 1.0, true, null], null, 'fp1;nl5[i1;s1:1d3ff0000000000000b1n]'],
    ['nested lists', fn() => [[1], [2, [3]]], null, 'fp1;nl2[l1[i1;]l2[i2;l1[i3;]]]'],
    ['list from string zero key', fn() => ['0' => 'x'], null, 'fp1;nl1[s1:x]'],

    // Maps
    ['map sorted by key', fn() => ['b' => 2, 'a' => 1], null, 'fp1;nm2{s1:ai1;s1:bi2;}'],
    ['map key tokens compare bytewise', fn() => ['aa' => 1, 'b' => 2], null, 'fp1;nm2{s1:bi2;s2:aai1;}'],
    ['map with int and string keys', fn() => [10 => 'x', 'a' => 'y', 2 => 'z'], null, 'fp1;nm3{i10;s1:xi2;s1:zs1:as1:y}'],
    ['sparse int keys are a map', fn() => [1 => 'a', 0 => 'b'], null, 'fp1;nm2{i0;s1:bi1;s1:a}'],
    ['int-like string key is cast by PHP', fn() => ['1' => 'x'], null, 'fp1;nm1{i1;s1:x}'],
    ['null value is kept', fn() => ['name' => null], null, 'fp1;nm1{s4:namen}'],
    [
        'nested maps and lists',
        fn() => ['z' => ['y' => 1, 'x' => [true, null]], 'a' => 0.5],
        null,
        'fp1;nm2{s1:ad3fe0000000000000s1:zm2{s1:xl2[b1n]s1:yi1;}}',
    ],
    [
        'list of maps',
        fn() => [['id' => 2, 'tag' => 'b'], ['tag' => 'a', 'id' => 1]],
        null,
        'fp1;nl2[m2{s2:idi2;s3:tags1:b}m2{s2:idi1;s3:tags1:a}]',
    ],
    // Filtering and path rules
    [
        'ignore a nested key',
        fn() => ['a' => 1, 'b' => ['c' => 2, 'd' => 3]],
        fn($b) => $b->ignore('b.c'),
        'fp1;nm2{s1:ai1;s1:bm1{s1:di3;}}',
    ],
    [
        'ignore with a wildcard',
        fn() => ['items' => [['id' => 1, 'at' => 'x'], ['id' => 2, 'at' => 'y']]],
        fn($b) => $b->ignore('items.*.at'),
        'fp1;nm1{s5:itemsl2[m1{s2:idi1;}m1{s2:idi2;}]}',
    ],
    ['ignore a list element re-indexes the list', fn() => [10, 20, 30], fn($b) => $b->ignore('1'), 'fp1;nl2[i10;i30;]'],
    ['a map emptied by ignore stays a map', fn() => ['m' => ['x' => 1]], fn($b) => $b->ignore('m.x'), 'fp1;nm1{s1:mm0{}}'],
    ['ignore an escaped dot', fn() => ['a.b' => 1, 'a' => ['b' => 2]], fn($b) => $b->ignore('a\.b'), 'fp1;nm1{s1:am1{s1:bi2;}}'],
    [
        'only with a wildcard',
        fn() => ['id' => 1, 'name' => 'n', 'address' => ['city' => 'c', 'zip' => 'z'], 'x' => true],
        fn($b) => $b->only(['id', 'address.*']),
        'fp1;nm2{s2:idi1;s7:addressm2{s3:zips1:zs4:citys1:c}}',
    ],
    [
        'only drops ancestors without a selected child',
        fn() => ['user' => ['name' => 'a'], 'id' => 1],
        fn($b) => $b->only(['user.email', 'id']),
        'fp1;nm1{s2:idi1;}',
    ],
    [
        'transform at a path',
        fn() => ['email' => ' A@B.C ', 'name' => ' N '],
        fn($b) => $b->at('email')->transform(fn($v) => strtolower(trim($v))),
        'fp1;nm2{s4:names3: N s5:emails5:a@b.c}',
    ],
    [
        'ignore nulls and the arrays they empty',
        fn() => ['a' => null, 'b' => ['c' => null], 'd' => 1],
        fn($b) => $b->ignoreNulls()->ignoreEmptyArrays(),
        'fp1;nm1{s1:di1;}',
    ],
    // List rules
    [
        'unordered orders elements by their bytes',
        fn() => ['tags' => ['php', 'laravel', 'api']],
        fn($b) => $b->at('tags')->unordered(),
        'fp1;nm1{s4:tagsl3[s3:apis3:phps7:laravel]}',
    ],
    ['unordered with mixed types', fn() => [2, 'a', 1, null, true], fn($b) => $b->unordered(), 'fp1;nl5[b1i1;i2;ns1:a]'],
    [
        'sortBy several fields and directions',
        fn() => [['cat' => 'b', 'p' => 1, 'id' => 3], ['cat' => 'a', 'p' => 2, 'id' => 2], ['cat' => 'a', 'p' => 5, 'id' => 1]],
        fn($b) => $b->sortBy(['cat' => 'asc', 'p' => 'desc']),
        'fp1;nl3[m3{s1:pi5;s2:idi1;s3:cats1:a}m3{s1:pi2;s2:idi2;s3:cats1:a}m3{s1:pi1;s2:idi3;s3:cats1:b}]',
    ],
    ['unique is strict', fn() => [1, '1', 1, true, '1'], fn($b) => $b->unique(), 'fp1;nl3[i1;s1:1b1]'],
    [
        'uniqueBy keeps the first occurrence after sorting',
        fn() => [['id' => 2, 'v' => 'x'], ['id' => 1, 'v' => 'y'], ['id' => 2, 'v' => 'z']],
        fn($b) => $b->sortBy('id')->uniqueBy('id'),
        'fp1;nl2[m2{s1:vs1:ys2:idi1;}m2{s1:vs1:xs2:idi2;}]',
    ],
    // Value rules
    [
        'trim and lower at a path',
        fn() => ['email' => "  Ab@X.io\t"],
        fn($b) => $b->at('email')->trim()->lower(),
        'fp1;nm1{s5:emails7:ab@x.io}',
    ],
    ['normalizeWhitespace', fn() => "  a \t b\n\nc  ", fn($b) => $b->normalizeWhitespace(), 'fp1;ns5:a b c'],
    ['lower is UTF-8 aware', fn() => 'ÉCOLE', fn($b) => $b->lower(), 'fp1;ns6:école'],
    ['precision rounds the binary value', fn() => [0.285, 1], fn($b) => $b->precision(2), 'fp1;nl2[d3fd1eb851eb851eci1;]'],
    [
        'normalizeNumericStrings converts plain decimals only',
        fn() => ['1', '1.50', '01', ' 1', '-0', '1e3'],
        fn($b) => $b->normalizeNumericStrings(),
        'fp1;nl6[i1;d3ff8000000000000s2:01s2: 1d0000000000000000d408f400000000000]',
    ],
    [
        'normalizeBooleans maps exactly eight values',
        fn() => [true, 1, '1', 'true', false, 0, '0', 'false', 'yes', 2],
        fn($b) => $b->normalizeBooleans(),
        'fp1;nl10[b1b1b1b1b0b0b0b0s3:yesi2;]',
    ],

    // Dates
    ['date as a UTC instant', fn() => new DateTimeImmutable('2026-10-12 10:00:00.123456+02:00'), null, 'fp1;nt2026-10-12T08:00:00.123456Z'],
    ['mutable date with the same instant', fn() => new DateTime('2026-10-12 08:00:00.123456+00:00'), null, 'fp1;nt2026-10-12T08:00:00.123456Z'],
    [
        'date truncated to the day',
        fn() => new DateTimeImmutable('2026-10-12 10:00:00.123456+02:00'),
        fn($b) => $b->datePrecision('day'),
        'fp1;nt2026-10-12T00:00:00.000000Z',
    ],
    [
        'date with its timezone kept',
        fn() => new DateTimeImmutable('2026-10-12 10:00:00.123456+02:00'),
        fn($b) => $b->keepTimezone(),
        'fp1;nz33:2026-10-12T10:00:00.123456 +02:00',
    ],

    // Objects
    ['stdClass is a map', fn() => (object) ['b' => 1, 'a' => 2], null, 'fp1;nm2{s1:ai2;s1:bi1;}'],
    ['empty stdClass is an empty map', fn() => new stdClass(), null, 'fp1;nm0{}'],
    ['stdClass with numeric properties stays a map', fn() => (object) ['x', 'y'], null, 'fp1;nm2{i0;s1:xi1;s1:y}'],
    ['ArrayObject', fn() => new ArrayObject(['b' => 1, 'a' => 2]), null, 'fp1;nm2{s1:ai2;s1:bi1;}'],
    ['ArrayIterator over a list', fn() => new ArrayIterator([1, 2]), null, 'fp1;nl2[i1;i2;]'],
    [
        'JsonSerializable',
        fn() => new class implements JsonSerializable {
            public function jsonSerialize(): mixed
            {
                return ['z' => 1];
            }
        },
        null,
        'fp1;nm1{s1:zi1;}',
    ],
    [
        'normalize() callback',
        fn() => ['tz' => new DateTimeZone('UTC')],
        fn($b) => $b->normalize(DateTimeZone::class, fn(DateTimeZone $zone) => $zone->getName()),
        'fp1;nm1{s2:tzs3:UTC}',
    ],
    // Namespaces
    ['namespace in the header', fn() => ['id' => 1], fn($b) => $b->namespace('invoice'), 'fp1;s7:invoicem1{s2:idi1;}'],
    ['namespace is length-framed', fn() => 'b', fn($b) => $b->namespace('a'), 'fp1;s1:as1:b'],

    // Enums (from 8.1.x)
    ['enum pure case', fn() => Suit::Hearts, null, 'fp1;nes47:Pharaonic\\Fingerprint\\Tests\\Fixtures\\Enums\\Suits6:Heartsn'],
    ['enum string-backed case', fn() => Status::Paid, null, 'fp1;nes49:Pharaonic\\Fingerprint\\Tests\\Fixtures\\Enums\\Statuss4:Paids4:paid'],
    ['enum int-backed case', fn() => Priority::High, null, 'fp1;nes51:Pharaonic\\Fingerprint\\Tests\\Fixtures\\Enums\\Prioritys4:Highi3;'],
    ['enum of another class with the same backed value', fn() => State::Paid, null, 'fp1;nes48:Pharaonic\\Fingerprint\\Tests\\Fixtures\\Enums\\States4:Paids4:paid'],
    ['enum in a map', fn() => ['status' => Status::Paid, 'id' => 1], null, 'fp1;nm2{s2:idi1;s6:statuses49:Pharaonic\\Fingerprint\\Tests\\Fixtures\\Enums\\Statuss4:Paids4:paid}'],
    ['enumsByName', fn() => Status::Paid, fn($b) => $b->enumsByName(), 'fp1;ns4:Paid'],
    ['enumsByValue with a string-backed case', fn() => Status::Paid, fn($b) => $b->enumsByValue(), 'fp1;ns4:paid'],
    ['enumsByValue with an int-backed case', fn() => Priority::High, fn($b) => $b->enumsByValue(), 'fp1;ni3;'],
    [
        'unordered list of enum cases',
        fn() => [Suit::Spades, Suit::Hearts],
        fn($b) => $b->unordered(),
        'fp1;nl2[es47:Pharaonic\\Fingerprint\\Tests\\Fixtures\\Enums\\Suits6:Heartsnes47:Pharaonic\\Fingerprint\\Tests\\Fixtures\\Enums\\Suits6:Spadesn]',
    ],
];
