<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\FingerprintBuilder;
use Pharaonic\Fingerprint\Support\Canonicalizer;

/**
 * The golden vectors shared by every PHP line, keyed by name
 *
 * @return array<string, array{callable(): mixed, (callable(FingerprintBuilder): FingerprintBuilder)|null, string}>
 */
function vectors(): array
{
    static $vectors = null;

    if ($vectors === null) {
        $vectors = [];

        foreach (require __DIR__ . '/Fixtures/vectors.php' as [$name, $input, $configure, $document]) {
            $vectors[$name] = [$input, $configure, $document];
        }
    }

    return $vectors;
}

/**
 * Vector names as a dataset (Pest 1 would call closures placed directly in a dataset)
 *
 * @return array<string, array{string}>
 */
function vectorNames(bool $unconfiguredOnly = false): array
{
    $names = array_keys(array_filter(vectors(), fn(array $vector) => ! $unconfiguredOnly || $vector[1] === null));

    return array_combine($names, array_map(fn(string $name) => [$name], $names));
}

function canonical(mixed $value): string
{
    return (new Canonicalizer())->canonicalize($value);
}
