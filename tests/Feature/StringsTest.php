<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Exceptions\MissingExtensionException;
use Pharaonic\Fingerprint\Exceptions\UnsupportedValueException;
use Pharaonic\Fingerprint\Fingerprint;

it('trims ASCII whitespace only', function () {
    expect(Fingerprint::for(" \t\n\r\x0B\x0Cx\x0C ")->trim()->make())->toBe(Fingerprint::make('x'))
        ->and(Fingerprint::for("\u{00A0}x\0")->trim()->make())->toBe(Fingerprint::make("\u{00A0}x\0"));
});

it('collapses whitespace runs without touching the 0x85 byte', function () {
    expect(Fingerprint::for("a\x85  b")->normalizeWhitespace()->make())->toBe(Fingerprint::make("a\x85 b"))
        ->and(Fingerprint::for(" \t ")->normalizeWhitespace()->make())->toBe(Fingerprint::make(''));
});

it('changes case with UTF-8 rules, whatever the locale', function () {
    $locale = setlocale(LC_ALL, '0');

    try {
        setlocale(LC_ALL, 'tr_TR.UTF-8', 'ar_EG.UTF-8');

        expect(Fingerprint::for('IÉ')->lower()->make())->toBe(Fingerprint::make('ié'))
            ->and(Fingerprint::for('ié')->upper()->make())->toBe(Fingerprint::make('IÉ'));
    } finally {
        setlocale(LC_ALL, (string) $locale);
    }
});

it('applies string rules in a fixed order whatever the call order', function () {
    expect(Fingerprint::for('  A  B ')->lower()->normalizeWhitespace()->trim()->make())
        ->toBe(Fingerprint::for('  A  B ')->trim()->normalizeWhitespace()->lower()->make())
        ->toBe(Fingerprint::make('a b'));
});

it('lets the last case rule win', function () {
    expect(Fingerprint::for('Ab')->lower()->upper()->make())->toBe(Fingerprint::make('AB'));
});

it('only touches strings', function () {
    $value = [1, 1.5, true, null, ['  X ']];

    expect(Fingerprint::for($value)->trim()->lower()->normalizeWhitespace()->make())
        ->toBe(Fingerprint::make([1, 1.5, true, null, ['x']]));
});

it('rejects case changes on invalid UTF-8 instead of replacing bytes', function () {
    (void) Fingerprint::for(['s' => "\xff"])->lower()->make();
})->throws(UnsupportedValueException::class, 'A string that is not valid UTF-8 cannot be case-folded or Unicode-normalized at path "s".');

it('applies Unicode normalization forms', function () {
    $decomposed = "e\u{0301}";

    expect(Fingerprint::for($decomposed)->unicode('NFC')->make())->toBe(Fingerprint::make("\u{00E9}"))
        ->and(Fingerprint::for("\u{00E9}")->unicode('NFD')->make())->toBe(Fingerprint::make($decomposed))
        ->and(Fingerprint::for("\u{FB01}")->unicode('NFKC')->make())->toBe(Fingerprint::make('fi'));
})->skip(! class_exists(Normalizer::class), 'Needs ext-intl or symfony/polyfill-intl-normalizer.');

it('needs a Normalizer implementation for unicode()', function () {
    // The dev tools install symfony/polyfill-intl-normalizer, so this runs in a PHP process that only autoloads src/.
    $script = sprintf(<<<'PHP'
        spl_autoload_register(function ($class) {
            $prefix = 'Pharaonic\\Fingerprint\\';
            if (strncmp($class, $prefix, strlen($prefix)) === 0) {
                require %s . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            }
        });
        try {
            Pharaonic\Fingerprint\Fingerprint::for('x')->unicode('NFC');
        } catch (Throwable $exception) {
            echo get_class($exception), ': ', $exception->getMessage();
        }
        PHP, var_export(dirname(__DIR__, 2) . '/src', true));

    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1');

    expect($output)->toBe(MissingExtensionException::class . ': unicode() needs ext-intl or symfony/polyfill-intl-normalizer.');
})->skip(extension_loaded('intl'), 'This PHP has ext-intl; CI runs this test on a job without it.');

it('rejects unknown Unicode forms', function () {
    (void) Fingerprint::for('x')->unicode('nfc');
})->throws(ConfigurationException::class, 'unicode() accepts NFC, NFD, NFKC or NFKD, "nfc" given.');
