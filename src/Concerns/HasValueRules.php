<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Concerns;

use Normalizer as IntlNormalizer;
use Pharaonic\Fingerprint\Exceptions\ConfigurationException;
use Pharaonic\Fingerprint\Exceptions\MissingExtensionException;
use Pharaonic\Fingerprint\Support\Path;
use Pharaonic\Fingerprint\Support\Rules;
use Pharaonic\Fingerprint\Support\Scalars;

/**
 * Path scoping and the value rules shared by fingerprint and signature builders
 *
 * Value rules apply to every value until at() is called, and to the values at the latest
 * at() path afterwards. For the same kind of rule, the last one declared for a path wins.
 *
 * @internal
 */
trait HasValueRules
{
    private Rules $rules;

    /** @var non-empty-list<string|null>|null */
    private ?array $scope = null;

    /**
     * Apply the following value rules to the values at this path
     */
    public function at(string $path): self
    {
        $builder = clone $this;
        $builder->scope = Path::parse($path);

        return $builder;
    }

    /**
     * Replace the value with the callback's result before it is fingerprinted
     *
     * The result is normalized like any other value, so it may be an array.
     *
     * @param callable(mixed $value, string $path): mixed $callback
     */
    public function transform(callable $callback): self
    {
        return $this->rule('transform', $callback);
    }

    /**
     * Remove ASCII whitespace (space, tab, line feed, carriage return, vertical tab, form feed) from both ends of strings
     */
    public function trim(): self
    {
        return $this->rule('trim', true);
    }

    /**
     * Turn every run of ASCII whitespace in strings into one space, with none at either end
     */
    public function normalizeWhitespace(): self
    {
        return $this->rule('whitespace', true);
    }

    /**
     * Lowercase strings (UTF-8, through mbstring)
     */
    public function lower(): self
    {
        return $this->rule('case', 'lower');
    }

    /**
     * Uppercase strings (UTF-8, through mbstring)
     */
    public function upper(): self
    {
        return $this->rule('case', 'upper');
    }

    /**
     * Apply a Unicode normalization form to strings: NFC, NFD, NFKC or NFKD
     *
     * Needs ext-intl or symfony/polyfill-intl-normalizer.
     */
    public function unicode(string $form): self
    {
        if (! in_array($form, Scalars::UNICODE_FORMS, true)) {
            throw new ConfigurationException(sprintf('unicode() accepts NFC, NFD, NFKC or NFKD, "%s" given.', $form));
        }

        if (! class_exists(IntlNormalizer::class)) {
            throw new MissingExtensionException('unicode() needs ext-intl or symfony/polyfill-intl-normalizer.');
        }

        return $this->rule('unicode', $form);
    }

    /**
     * Round floats to a number of decimals, from their exact binary value: 0.285 becomes 0.28
     */
    public function precision(int $digits): self
    {
        if ($digits < 0 || $digits > 53) {
            throw new ConfigurationException(sprintf('precision() accepts 0 to 53 digits, %d given.', $digits));
        }

        return $this->rule('precision', $digits);
    }

    private function rule(string $kind, mixed $argument): self
    {
        $builder = clone $this;
        $builder->rules->values[] = [$this->scope, $kind, $argument];

        return $builder;
    }
}
