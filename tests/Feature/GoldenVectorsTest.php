<?php

declare(strict_types=1);

use Pharaonic\Fingerprint\Fingerprint;

it('produces the canonical v1 document', function (string $name) {
    [$input, $configure, $document] = vectors()[$name];
    $builder = Fingerprint::for($input());

    expect(builderCanonical($configure ? $configure($builder) : $builder))->toBe($document);
})->with(vectorNames());

it('hashes the canonical v1 document with SHA-256', function (string $name) {
    [$input, $configure, $document] = vectors()[$name];
    $builder = Fingerprint::for($input());

    expect(($configure ? $configure($builder) : $builder)->make())->toBe(hash('sha256', $document));
})->with(vectorNames());

it('matches the plain entry point when nothing is configured', function (string $name) {
    [$input, , $document] = vectors()[$name];

    expect(Fingerprint::make($input()))->toBe(hash('sha256', $document));
})->with(vectorNames(true));

it('gives every vector a distinct document unless it is meant to equal another', function () {
    $documents = array_column(vectors(), 2);

    // Negative zero folds into zero; both vectors exist on purpose.
    expect(count(array_unique($documents)))->toBe(count($documents) - 1);
});
