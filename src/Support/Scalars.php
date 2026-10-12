<?php

declare(strict_types=1);

namespace Pharaonic\Fingerprint\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Normalizer as IntlNormalizer;
use Pharaonic\Fingerprint\Exceptions\UnsupportedValueException;

/**
 * Value rules for strings, numbers, booleans and dates
 *
 * Every rule only touches values of its own type and is independent of ini settings, the locale
 * and the PHP version.
 *
 * @internal
 */
final class Scalars
{
    /** The ASCII whitespace used by trim() and normalizeWhitespace() */
    public const WHITESPACE = " \t\n\r\x0B\x0C";

    public const UNICODE_FORMS = ['NFC', 'NFD', 'NFKC', 'NFKD'];

    public const DATE_UNITS = ['microsecond', 'second', 'minute', 'hour', 'day'];

    /**
     * @param array<string, mixed> $rules
     * @param list<int|string>|string $path Segments, formatted only when an error needs them.
     */
    public static function apply(mixed $value, array $rules, array|string $path): mixed
    {
        if ($rules === [] && ! $value instanceof DateTimeInterface) {
            return $value; // Dates are always converted, even without rules.
        }

        if (is_string($value)) {
            $value = self::string($value, $rules, $path);
        }

        if (is_string($value) && isset($rules['numericStrings'])) {
            $value = self::numericString($value);
        }

        if (is_float($value) && is_int($rules['precision'] ?? null)) {
            $value = self::round($value, $rules['precision']);
        }

        if (isset($rules['booleans'])) {
            $value = self::boolean($value);
        }

        if ($value instanceof DateTimeInterface) {
            $unit = $rules['datePrecision'] ?? 'microsecond';

            $value = self::date($value, is_string($unit) ? $unit : 'microsecond', isset($rules['keepTimezone']));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $rules
     * @param list<int|string>|string $path
     */
    private static function string(string $value, array $rules, array|string $path): string
    {
        if (isset($rules['trim'])) {
            $value = trim($value, self::WHITESPACE);
        }

        if (isset($rules['whitespace'])) {
            $value = self::collapseWhitespace($value);
        }

        $form = $rules['unicode'] ?? null;
        $case = $rules['case'] ?? null;

        if (($form !== null || $case !== null) && ! mb_check_encoding($value, 'UTF-8')) {
            throw UnsupportedValueException::at(is_array($path) ? Path::format($path) : $path, 'A string that is not valid UTF-8 cannot be case-folded or Unicode-normalized');
        }

        if (is_string($form)) {
            $forms = ['NFC' => IntlNormalizer::FORM_C, 'NFD' => IntlNormalizer::FORM_D, 'NFKC' => IntlNormalizer::FORM_KC, 'NFKD' => IntlNormalizer::FORM_KD];
            $normalized = IntlNormalizer::normalize($value, $forms[$form] ?? IntlNormalizer::FORM_C);

            if (! is_string($normalized)) {
                throw UnsupportedValueException::at(is_array($path) ? Path::format($path) : $path, 'The string could not be Unicode-normalized');
            }

            $value = $normalized;
        }

        if ($case === 'lower') {
            $value = mb_strtolower($value, 'UTF-8');
        } elseif ($case === 'upper') {
            $value = mb_strtoupper($value, 'UTF-8');
        }

        return $value;
    }

    /**
     * Runs of ASCII whitespace become one space, with none at either end
     */
    private static function collapseWhitespace(string $value): string
    {
        $words = [];
        $length = strlen($value);
        $i = strspn($value, self::WHITESPACE);

        while ($i < $length) {
            $word = strcspn($value, self::WHITESPACE, $i);
            $words[] = substr($value, $i, $word);
            $i += $word;
            $i += strspn($value, self::WHITESPACE, $i);
        }

        return implode(' ', $words);
    }

    /**
     * Plain decimal literals become numbers: integers that fit become int, the rest float
     */
    private static function numericString(string $value): string|int|float
    {
        if (preg_match('/^-?(?:0|[1-9][0-9]*)$/', $value) === 1) {
            $int = (int) $value;

            return (string) $int === $value ? $int : (float) $value;
        }

        if (preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?$/', $value) === 1) {
            return (float) $value;
        }

        return $value;
    }

    /**
     * Round to a number of decimals from the exact binary value (sprintf, not round(),
     * whose results changed in PHP 8.4), so 0.285 becomes 0.28
     */
    private static function round(float $value, int $digits): float
    {
        if (is_infinite($value)) {
            return $value;
        }

        return (float) sprintf('%.' . $digits . 'F', $value);
    }

    private static function boolean(mixed $value): mixed
    {
        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        if ($value === false || $value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        return $value;
    }

    /**
     * The same instant always gives the same Date, whatever its timezone; keepTimezone()
     * keeps the local time and the timezone name instead
     */
    private static function date(DateTimeInterface $value, string $unit, bool $keepTimezone): Date
    {
        $date = DateTimeImmutable::createFromInterface($value);

        if (! $keepTimezone) {
            $date = $date->setTimezone(new DateTimeZone('UTC'));
        }

        $formats = [
            'microsecond' => 'Y-m-d\TH:i:s.u',
            'second' => 'Y-m-d\TH:i:s\.\0\0\0\0\0\0',
            'minute' => 'Y-m-d\TH:i\:\0\0\.\0\0\0\0\0\0',
            'hour' => 'Y-m-d\TH\:\0\0\:\0\0\.\0\0\0\0\0\0',
            'day' => 'Y-m-d\T\0\0\:\0\0\:\0\0\.\0\0\0\0\0\0',
        ];

        $time = $date->format($formats[$unit] ?? $formats['microsecond']);

        return new Date($time, $keepTimezone ? $date->getTimezone()->getName() : null);
    }
}
