<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

/**
 * An associative array in a normalized tree
 *
 * Lists are plain arrays there, so a map keeps its kind even when filtering empties it.
 *
 * @internal
 */
final readonly class Map
{
    /**
     * @param array<int|string, mixed> $items
     */
    public function __construct(public array $items) {}
}
