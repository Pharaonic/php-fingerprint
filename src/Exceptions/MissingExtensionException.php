<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Exceptions;

use LogicException;

/**
 * Thrown when a rule needs a PHP extension that is not loaded, such as intl for unicode().
 */
final class MissingExtensionException extends LogicException implements FingerprintException
{
}
