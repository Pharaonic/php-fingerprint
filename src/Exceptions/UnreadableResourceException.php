<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Exceptions;

use RuntimeException;

/**
 * Thrown when a file or stream cannot be read for a fingerprint.
 */
final class UnreadableResourceException extends RuntimeException implements FingerprintException
{
}
