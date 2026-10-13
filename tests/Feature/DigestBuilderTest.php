<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\DigestBuilder;
use Pharaonic\Fingerprint\Exceptions\UnreadableResourceException;
use Pharaonic\Fingerprint\Fingerprint;

beforeEach(function () {
    $this->path = (string) tempnam(sys_get_temp_dir(), 'fp');
    file_put_contents($this->path, "raw bytes\0\xff");
});

afterEach(function () {
    @unlink($this->path);
});

it('hashes a file\'s raw bytes', function () {
    expect(Fingerprint::file($this->path))->toBeInstanceOf(DigestBuilder::class)
        ->and(Fingerprint::file($this->path)->make())->toBe(hash_file('sha256', $this->path))
        ->and(Fingerprint::file($this->path)->algorithm('sha512')->hmac('key')->make())->toBe(hash_hmac_file('sha512', $this->path, 'key'))
        ->and(Fingerprint::file($this->path)->base64Url()->make())->toBe(rtrim(strtr(base64_encode(hash_file('sha256', $this->path, true)), '+/', '-_'), '='));
});

it('never canonicalizes file bytes', function () {
    expect(Fingerprint::file($this->path)->make())->not->toBe(Fingerprint::make("raw bytes\0\xff"));
});

it('hashes an empty file', function () {
    file_put_contents($this->path, '');

    expect(Fingerprint::file($this->path)->make())->toBe(hash('sha256', ''));
});

it('hashes a stream from its current position without rewinding or closing it', function () {
    $stream = fopen('php://memory', 'w+b');
    fwrite($stream, 'headerbody');
    fseek($stream, 6);

    $builder = Fingerprint::stream($stream);

    expect($builder->make())->toBe(hash('sha256', 'body'))
        ->and(is_resource($stream))->toBeTrue()
        ->and($builder->make())->toBe(hash('sha256', ''));

    rewind($stream);

    expect($builder->hmac('key')->make())->toBe(hash_hmac('sha256', 'headerbody', 'key'));

    fclose($stream);
});

it('verifies file fingerprints in constant time', function () {
    $hash = hash_file('sha256', $this->path);

    expect(Fingerprint::file($this->path)->verify($hash))->toBeTrue()
        ->and(Fingerprint::file($this->path)->verify(strtoupper($hash)))->toBeTrue()
        ->and(Fingerprint::file($this->path)->verify(hash('sha256', 'other')))->toBeFalse();
});

it('rejects missing files and directories when reading', function (string $path) {
    $builder = Fingerprint::file($path);

    $builder->make();
})->throws(UnreadableResourceException::class, 'does not exist or cannot be read')->with([
    'missing' => [__DIR__ . '/missing-file'],
    'directory' => [__DIR__],
]);

// Pest calls dataset closures first, so each test receives the value a closure builds.
it('rejects values that are not readable streams', function (mixed $value) {
    Fingerprint::stream($value);
})->throws(UnreadableResourceException::class)->with([
    'not a resource' => ['php://memory'],
    'closed stream' => [function () {
        $stream = fopen('php://memory', 'rb');
        fclose($stream);

        return $stream;
    }],
    'write-only stream' => [fn() => fopen('php://output', 'wb')],
]);

it('keeps the secret out of debug output', function () {
    $builder = Fingerprint::file($this->path)->hmac('file-secret-key');

    expect(print_r($builder, true))->not->toContain('file-secret-key')
        ->and($builder->__debugInfo())->toBe(['algorithm' => 'sha256', 'encoding' => 'hex', 'hmac' => true])
        ->and(fn() => serialize($builder))->toThrow(LogicException::class);
});

it('streams large files without loading them into memory', function () {
    $chunk = str_repeat('0123456789abcdef', 65536); // 1 MiB
    $handle = fopen($this->path, 'wb');

    for ($i = 0; $i < 64; $i++) {
        fwrite($handle, $chunk);
    }

    fclose($handle);
    unset($chunk);

    $before = memory_get_peak_usage();
    $file = Fingerprint::file($this->path)->make();
    $stream = fopen($this->path, 'rb');
    $streamed = Fingerprint::stream($stream)->hmac('k')->make();
    fclose($stream);

    expect(memory_get_peak_usage() - $before)->toBeLessThan(2 * 1024 * 1024)
        ->and($file)->toBe(hash_file('sha256', $this->path))
        ->and($streamed)->toBe(hash_hmac_file('sha256', $this->path, 'k'));
});
