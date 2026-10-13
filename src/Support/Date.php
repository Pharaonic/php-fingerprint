<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

/**
 * A date in a normalized tree: a UTC instant, or a local time with its timezone
 *
 * @internal
 */
final class Date
{
    public function __construct(
        public string $time,
        public ?string $zone = null,
    ) {}
}
