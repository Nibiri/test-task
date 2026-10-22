<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class FieldValue
{
    public const NAMES = ['title', 'status', 'amount'];

    public static function canonical(string $field, mixed $value): string
    {
        if (! in_array($field, self::NAMES, true)) {
            throw new InvalidArgumentException('Unknown deal field.');
        }

        if ($field === 'amount') {
            return self::canonicalAmount($value);
        }

        return trim((string) $value);
    }

    public static function canonicalAmount(mixed $value): string
    {
        if (is_int($value) || (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', trim($value)) === 1)) {
            $raw = trim((string) $value);
            $negative = str_starts_with($raw, '-');
            $raw = ltrim($raw, '-');
            [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
            $fraction = str_pad($fraction, 3, '0');
            $cents = ((int) $whole) * 100 + (int) substr($fraction, 0, 2);
            if ((int) $fraction[2] >= 5) {
                $cents++;
            }
            $sign = $negative ? '-' : '';

            return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
        }

        if (is_float($value)) {
            return number_format($value, 2, '.', '');
        }

        throw new InvalidArgumentException('Invalid amount.');
    }
}
