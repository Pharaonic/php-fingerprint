<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a builder is configured with an invalid path, rule, callback result or option.
 */
final class ConfigurationException extends InvalidArgumentException implements FingerprintException
{
}
