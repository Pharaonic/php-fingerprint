<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Exceptions\MissingFieldException;
use Pharaonic\Fingerprint\Exceptions\UnsupportedValueException;
use Pharaonic\Fingerprint\Fingerprint;
use Pharaonic\Fingerprint\SignatureBuilder;

$payload = ['b' => 'two', 'a' => 'one', 'n' => 3, 'ok' => true, 'order' => ['id' => 9, 'note' => null]];

it('starts a signature builder', function () use ($payload) {
    expect(Fingerprint::signature($payload))->toBeInstanceOf(SignatureBuilder::class);
});

it('joins field values in the declared order', function () use ($payload) {
    expect(Fingerprint::signature($payload)->fields(['a', 'b', 'n', 'ok', 'order.id'])->message())->toBe('onetwo3true9')
        ->and(Fingerprint::signature($payload)->fields(['order.id', 'ok', 'n', 'b', 'a'])->message())->toBe('9true3twoone');
});

it('never sorts the payload', function () use ($payload) {
    $reversed = array_reverse($payload, true);

    expect(Fingerprint::signature($reversed)->fields(['b', 'a'])->message())
        ->toBe(Fingerprint::signature($payload)->fields(['b', 'a'])->message())
        ->toBe('twoone');
});

it('changes the signature when the field order changes', function () use ($payload) {
    expect(Fingerprint::signature($payload)->fields(['a', 'b'])->make())
        ->not->toBe(Fingerprint::signature($payload)->fields(['b', 'a'])->make());
});

it('replaces the fields when fields() is called again', function () use ($payload) {
    expect(Fingerprint::signature($payload)->fields(['a'])->fields(['b'])->message())->toBe('two');
});

it('uses separators', function () use ($payload) {
    $signature = Fingerprint::signature($payload)->fields(['a', 'b']);

    expect($signature->separator('|')->message())->toBe('one|two')
        ->and($signature->keyValue()->message())->toBe('a=oneb=two')
        ->and($signature->keyValue()->separator('&')->message())->toBe('a=one&b=two')
        ->and($signature->keyValue()->keyValueSeparator(':')->separator(',')->message())->toBe('a:one,b:two')
        ->and($signature->keyValue()->valuesOnly()->message())->toBe('onetwo');
});

it('renames fields in key-value output', function () use ($payload) {
    expect(Fingerprint::signature($payload)->fields(['first' => 'a', 'order.id'])->keyValue()->separator('&')->message())
        ->toBe('first=one&order.id=9');
});

it('throws for a missing field by default', function () use ($payload) {
    Fingerprint::signature($payload)->fields(['a', 'order.missing'])->message();
})->throws(MissingFieldException::class, 'The field "order.missing" is missing from the payload.');

it('treats a path through a null or a scalar as missing', function () use ($payload) {
    expect(Fingerprint::signature($payload)->fields(['order.note.x', 'n.x'])->onMissing('skip')->message())->toBe('');
});

it('handles missing fields as configured', function (string $strategy, string $message) use ($payload) {
    expect(Fingerprint::signature($payload)->fields(['a', 'zzz', 'b'])->separator('|')->onMissing($strategy)->message())->toBe($message);
})->with([
    'empty' => ['empty', 'one||two'],
    'null' => ['null', 'one|null|two'],
    'skip' => ['skip', 'one|two'],
]);

it('throws for a null field by default', function () use ($payload) {
    Fingerprint::signature($payload)->fields(['order.note'])->message();
})->throws(UnsupportedValueException::class, 'The field "order.note" is null; choose how to sign it with onNull().');

it('handles null fields as configured, apart from missing ones', function () use ($payload) {
    $signature = Fingerprint::signature($payload)->fields(['order.note', 'zzz'])->separator('|');

    expect($signature->onNull('empty')->onMissing('null')->message())->toBe('|null')
        ->and($signature->onNull('null')->onMissing('skip')->message())->toBe('null')
        ->and($signature->onNull('skip')->onMissing('empty')->message())->toBe('');
});

it('resolves missing and null fields with closures', function () use ($payload) {
    $signature = Fingerprint::signature($payload)->fields(['order.note', 'zzz', 'a'])->separator('|')
        ->onNull(fn (string $field) => "<$field>")
        ->onMissing(fn (string $field) => null);

    expect($signature->message())->toBe('<order.note>|one');
});

it('rejects a strategy closure that returns something else than a string or null', function () use ($payload) {
    Fingerprint::signature($payload)->fields(['zzz'])->onMissing(fn () => 1)->message();
})->throws(ConfigurationException::class, 'A field strategy must return a string or null, int returned for "zzz".');

it('turns values into strings', function () {
    $payload = ['s' => 'x', 'i' => -5, 't' => true, 'f' => false, 'p' => 10.5, 'e' => ''];

    expect(Fingerprint::signature($payload)->fields(['s', 'i', 't', 'f', 'p', 'e'])->separator(',')->at('p')->precision(2)->message())
        ->toBe('x,-5,true,false,10.50,');
});

it('needs precision() for floats', function () {
    Fingerprint::signature(['amount' => 10.5])->fields(['amount'])->message();
})->throws(UnsupportedValueException::class, 'The field "amount" holds float; set precision() for it.');

it('needs transform() for arrays, objects and dates', function (mixed $value) {
    Fingerprint::signature(['v' => $value])->fields(['v'])->message();
})->throws(UnsupportedValueException::class, 'convert it with transform()')->with([
    'array' => [[1]],
    'date' => [new DateTimeImmutable()],
]);

it('rejects infinite floats even with precision()', function () {
    Fingerprint::signature(['v' => INF])->fields(['v'])->precision(2)->message();
})->throws(UnsupportedValueException::class);

it('applies per-field rules without changing the order', function () {
    $payload = ['email' => ' Me@X.IO ', 'currency' => 'egp', 'items' => [1, 2]];

    $message = Fingerprint::signature($payload)
        ->fields(['currency', 'email', 'items'])
        ->separator('|')
        ->at('email')->trim()->lower()
        ->at('currency')->upper()
        ->at('items')->transform(fn (array $items) => implode('-', $items))
        ->message();

    expect($message)->toBe('EGP|me@x.io|1-2');
});

it('reads stdClass, ArrayAccess and JsonSerializable payloads', function () {
    $json = json_decode('{"order":{"id":7,"amount":100}}');
    $access = new ArrayObject(['order' => new ArrayObject(['id' => 7, 'amount' => 100])]);
    $serializable = new class () implements JsonSerializable {
        public function jsonSerialize(): mixed
        {
            return ['order' => ['id' => 7, 'amount' => 100]];
        }
    };

    foreach ([$json, $access, $serializable] as $payload) {
        expect(Fingerprint::signature($payload)->fields(['order.amount', 'order.id'])->message())->toBe('1007');
    }
});

it('never reads private or protected properties', function () {
    $payload = new class () {
        public string $visible = 'v';

        private string $hidden = 'h';

        public function hidden(): string
        {
            return $this->hidden;
        }
    };

    expect(Fingerprint::signature($payload)->fields(['visible'])->message())->toBe('v')
        ->and(fn () => Fingerprint::signature($payload)->fields(['hidden'])->message())->toThrow(MissingFieldException::class);
});

it('hashes and signs the message', function () use ($payload) {
    $signature = Fingerprint::signature($payload)->fields(['a', 'b']);

    expect($signature->make())->toBe(hash('sha256', 'onetwo'))
        ->and($signature->algorithm('sha512')->hmac('key')->make())->toBe(hash_hmac('sha512', 'onetwo', 'key'))
        ->and($signature->base64()->make())->toBe(base64_encode(hash('sha256', 'onetwo', true)));
});

it('verifies signatures in constant time', function () use ($payload) {
    $signature = Fingerprint::signature($payload)->fields(['a', 'b'])->hmac('key');
    $expected = $signature->make();

    expect($signature->verify($expected))->toBeTrue()
        ->and($signature->verify(strtoupper($expected)))->toBeTrue()
        ->and($signature->hmac('other')->verify($expected))->toBeFalse()
        ->and($signature->fields(['b', 'a'])->verify($expected))->toBeFalse();
});

it('never matches a canonical fingerprint of the same data', function () {
    expect(Fingerprint::signature(['a' => 'x'])->fields(['a'])->make())->not->toBe(Fingerprint::make(['a' => 'x']))
        ->and(Fingerprint::signature(['a' => 'x'])->fields(['a'])->make())->not->toBe(Fingerprint::make('x'));
});

it('keeps the secret out of debug output and refuses serialization', function () {
    $signature = Fingerprint::signature(['a' => 1])->fields(['a'])->hmac('top-secret-key');

    expect(print_r($signature, true))->not->toContain('top-secret-key')
        ->and($signature->__debugInfo())->toBe(['algorithm' => 'sha256', 'encoding' => 'hex', 'hmac' => true, 'fields' => ['a']])
        ->and(fn () => serialize($signature))->toThrow(LogicException::class);
});

it('never changes the builder a method is called on', function () use ($payload) {
    $base = Fingerprint::signature($payload)->fields(['a', 'b']);
    $before = $base->make();

    $base->fields(['b'])->keyValue()->separator('|')->keyValueSeparator(':')->onMissing('skip')->onNull('empty')
        ->at('a')->upper()->algorithm('md5')->hmac('k')->base64();

    expect($base->make())->toBe($before);
});

$invalid = [
    'no fields' => fn ($s) => $s->message(),
    'empty fields' => fn ($s) => $s->fields([])->message(),
    'wildcard field' => fn ($s) => $s->fields(['items.*.id']),
    'non-string field' => fn ($s) => $s->fields([1]),
    'invalid path' => fn ($s) => $s->fields(['a..b']),
    'function name as a strategy' => fn ($s) => $s->onMissing('trim'),
    'unknown null strategy' => fn ($s) => $s->onNull('zero'),
];

it('rejects invalid signature configurations', function (string $case) use ($invalid, $payload) {
    $invalid[$case](Fingerprint::signature($payload));
})->throws(ConfigurationException::class)->with(array_keys($invalid));
