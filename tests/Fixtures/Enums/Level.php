<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Tests\Fixtures\Enums;

use JsonSerializable;

enum Level: int implements JsonSerializable
{
    case Debug = 1;

    public function jsonSerialize(): mixed
    {
        return ['level' => $this->name];
    }
}
