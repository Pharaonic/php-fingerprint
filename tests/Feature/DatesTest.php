<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Fingerprint;

it('fingerprints the same instant the same in any timezone', function () {
    $cairo = new DateTimeImmutable('2026-01-15 10:00:00', new DateTimeZone('Africa/Cairo'));
    $utc = new DateTime('2026-01-15 08:00:00', new DateTimeZone('UTC'));

    expect(Fingerprint::make(['at' => $cairo]))->toBe(Fingerprint::make(['at' => $utc]));
});

it('keeps microseconds by default', function () {
    expect(Fingerprint::make(new DateTimeImmutable('2026-01-15 08:00:00.000001+00:00')))
        ->not->toBe(Fingerprint::make(new DateTimeImmutable('2026-01-15 08:00:00+00:00')));
});

it('truncates dates to a unit', function (string $unit, string $a, string $b, bool $equal) {
    $hash = fn(string $date) => Fingerprint::for(new DateTimeImmutable($date))->datePrecision($unit)->make();

    expect($hash($a) === $hash($b))->toBe($equal);
})->with([
    'second equal' => ['second', '2026-01-15 08:00:00.1+00:00', '2026-01-15 08:00:00.9+00:00', true],
    'second differs' => ['second', '2026-01-15 08:00:00+00:00', '2026-01-15 08:00:01+00:00', false],
    'minute equal' => ['minute', '2026-01-15 08:00:01+00:00', '2026-01-15 08:00:59+00:00', true],
    'hour equal' => ['hour', '2026-01-15 08:01:00+00:00', '2026-01-15 08:59:00+00:00', true],
    'day equal' => ['day', '2026-01-15 00:00:00+00:00', '2026-01-15 23:59:59+00:00', true],
    'day in UTC' => ['day', '2026-01-15 01:00:00+02:00', '2026-01-15 03:00:00+02:00', false],
    'day differs' => ['day', '2026-01-15 23:59:59+00:00', '2026-01-16 00:00:00+00:00', false],
]);

it('tells timezones apart when they are kept', function () {
    $cairo = new DateTimeImmutable('2026-01-15 10:00:00', new DateTimeZone('Africa/Cairo'));
    $utc = new DateTimeImmutable('2026-01-15 08:00:00', new DateTimeZone('UTC'));

    expect(Fingerprint::for($cairo)->keepTimezone()->make())->not->toBe(Fingerprint::for($utc)->keepTimezone()->make())
        ->and(Fingerprint::for($cairo)->keepTimezone()->make())->toBe(Fingerprint::for(clone $cairo)->keepTimezone()->make());
});

it('scopes date rules to a path', function () {
    $value = ['a' => new DateTimeImmutable('2026-01-15 08:00:30+00:00'), 'b' => new DateTimeImmutable('2026-01-15 08:00:30+00:00')];

    expect(Fingerprint::for($value)->at('a')->datePrecision('minute')->make())
        ->toBe(Fingerprint::make(['a' => new DateTimeImmutable('2026-01-15 08:00:00+00:00'), 'b' => $value['b']]));
});

it('never changes the original date', function () {
    $date = new DateTime('2026-01-15 10:00:00', new DateTimeZone('Africa/Cairo'));

    (void) Fingerprint::for($date)->datePrecision('day')->make();

    expect($date->format('Y-m-d H:i:s e'))->toBe('2026-01-15 10:00:00 Africa/Cairo');
});

it('sorts lists of dates chronologically', function () {
    $later = new DateTimeImmutable('2026-01-16 00:00:00+00:00');
    $earlier = new DateTimeImmutable('2026-01-15 00:00:00+00:00');

    expect(Fingerprint::for([['at' => $later], ['at' => $earlier]])->sortBy('at')->make())
        ->toBe(Fingerprint::make([['at' => $earlier], ['at' => $later]]));
});

it('rejects unknown date units', function () {
    (void) Fingerprint::for(null)->datePrecision('week');
})->throws(ConfigurationException::class, 'datePrecision() accepts microsecond, second, minute, hour or day, "week" given.');
