<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Tests\Fixtures\Enums;

enum Status: string
{
    case Pending = 'pending';
    case Paid = 'paid';
}
