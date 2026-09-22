<?php

declare(strict_types=1);

namespace App\Domains\Orders\Support;

use App\Domains\Shared\Support\Clock;

final class OrderNumberGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(private readonly Clock $clock) {}

    public function next(): string
    {
        $prefix = strtoupper((string) config('order.number_prefix', 'ORD'));
        $length = max(4, min(12, (int) config('order.number_suffix_length', 6)));
        $date = $this->clock->now()->format('Ymd');
        $alphabetLength = strlen(self::ALPHABET);
        $bytes = random_bytes($length);
        $suffix = '';

        for ($index = 0; $index < $length; $index++) {
            $suffix .= self::ALPHABET[ord($bytes[$index]) % $alphabetLength];
        }

        return $prefix.'-'.$date.'-'.$suffix;
    }
}
