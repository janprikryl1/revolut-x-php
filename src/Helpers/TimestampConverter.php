<?php

declare(strict_types=1);

namespace RevolutX\Helpers;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

/**
 * Converts a point in time into a Unix timestamp in milliseconds.
 *
 * The Revolut X API expresses every time bound in milliseconds, which is easy
 * to get wrong by a factor of 1000.  This converter lets the SDK accept
 * DateTimeInterface objects and date strings as well, so callers rarely have
 * to do the arithmetic themselves.
 */
class TimestampConverter
{
    /**
     * Minimum number of digits a digits-only string needs before it is read as
     * a millisecond timestamp.
     *
     * 12 digits reach back to 1973 and real timestamps have had 13 since 2001,
     * so anything shorter is almost certainly a date that lost its separators
     * ('2024', '20240101') rather than an instant in 1970.
     */
    public const MIN_MS_DIGITS = 12;

    /**
     * Converts a point in time to a Unix timestamp in milliseconds.
     *
     * Accepted input:
     *
     * - `int` — already a timestamp in milliseconds; returned unchanged.
     *   Seconds are *not* auto-detected, multiply by 1000 yourself.
     * - `float` — the same, truncated towards zero.
     * - `DateTimeInterface` — converted using the object's own timezone,
     *   with millisecond precision.
     * - a digits-only `string` (optionally signed) of at least
     *   {@see self::MIN_MS_DIGITS} digits — milliseconds, as above.  Useful for
     *   values arriving from JSON or query parameters.  A shorter run of digits
     *   is rejected rather than read as an instant in 1970.
     * - any other `string` — parsed by {@see DateTimeImmutable}, so ISO-8601
     *   ('2024-01-01T00:00:00Z'), plain dates ('2024-01-01') and relative
     *   expressions ('-7 days') all work.  A string without an explicit
     *   offset is interpreted as **UTC**, never as the server's local
     *   timezone.
     *
     * @param int|float|string|DateTimeInterface $value Point in time to convert.
     * @return int Unix timestamp in milliseconds.
     *
     * @throws InvalidArgumentException If the value is not a finite number, or
     *                                 is a string that cannot be parsed.
     */
    public static function toMilliseconds(int|float|string|DateTimeInterface $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new InvalidArgumentException(
                    'Timestamp must be a finite number, got ' . var_export($value, true) . '.'
                );
            }
            return (int) $value;
        }

        if (is_string($value)) {
            return self::fromString($value);
        }

        return self::fromDateTime($value);
    }

    /**
     * Converts a value to milliseconds, passing null through untouched.
     *
     * Convenience wrapper for the SDK's optional time range arguments.
     *
     * @param int|float|string|DateTimeInterface|null $value Point in time, or null.
     *
     * @throws InvalidArgumentException If the value cannot be converted.
     */
    public static function toMillisecondsOrNull(
        int|float|string|DateTimeInterface|null $value
    ): ?int {
        return $value === null ? null : self::toMilliseconds($value);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function fromString(string $value): int
    {
        $trimmed = trim($value);

        // An empty string would be read as 'now' by DateTimeImmutable, which
        // is never what the caller meant.
        if ($trimmed === '') {
            throw new InvalidArgumentException('Timestamp string must not be empty.');
        }

        if (preg_match('/^[+-]?(\d+)$/', $trimmed, $matches) === 1) {
            if (strlen($matches[1]) >= self::MIN_MS_DIGITS) {
                return (int) $trimmed;
            }

            throw new InvalidArgumentException(
                "Ambiguous numeric timestamp string: '{$value}'. A digits-only string is read "
                . 'as Unix milliseconds and needs at least ' . self::MIN_MS_DIGITS
                . ' digits — anything shorter lands in 1970 and is far more likely a date '
                . "that lost its separators. Pass an int for milliseconds, or a date like "
                . "'2024-01-01'."
            );
        }

        try {
            $parsed = new DateTimeImmutable($trimmed, new DateTimeZone('UTC'));
        } catch (Exception $e) {
            throw new InvalidArgumentException("Unparsable timestamp string: '{$value}'.", 0, $e);
        }

        return self::fromDateTime($parsed);
    }

    private static function fromDateTime(DateTimeInterface $value): int
    {
        // Built from the two parts rather than format('Uv') so that instants
        // before the epoch stay correct: there 'U' is negative while 'u' is
        // still a positive offset into that second.
        $seconds = (int) $value->format('U');
        $micros = (int) $value->format('u');

        return $seconds * 1000 + intdiv($micros, 1000);
    }
}
