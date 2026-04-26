<?php

declare(strict_types=1);

/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Performance\FreeDSx\Asn1\Support;

use InvalidArgumentException;

/**
 * Narrowing helpers for `mixed` values from Symfony Console option parsing
 * and JSON decoding. Centralized so we type-check once and the call sites stay clean.
 */
final class Cast
{
    public static function toInt(mixed $value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf(
                'Expected numeric, got %s.',
                get_debug_type($value),
            ));
        }

        return (int) $value;
    }

    public static function toFloat(mixed $value, float $default = 0.0): float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf(
                'Expected numeric, got %s.',
                get_debug_type($value),
            ));
        }

        return (float) $value;
    }

    public static function toBool(mixed $value): bool
    {
        return (bool) $value;
    }

    public static function toString(mixed $value, string $default = ''): string
    {
        if ($value === null) {
            return $default;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        throw new InvalidArgumentException(sprintf(
            'Expected scalar, got %s.',
            get_debug_type($value),
        ));
    }

    public static function toStringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::toString($value);
    }

    /**
     * @return list<string>
     */
    public static function toStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $entry) {
            if (is_string($entry) && $entry !== '') {
                $out[] = $entry;
            }
        }

        return $out;
    }
}
