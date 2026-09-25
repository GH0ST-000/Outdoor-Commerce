<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Support;

use App\Domains\Shared\Support\Clock;

final class ShipmentNumberGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(private readonly Clock $clock) {}

    public function next(): string
    {
        $prefix = strtoupper((string) config('shipping.number_prefix', 'SHP'));
        $length = max(4, min(12, (int) config('shipping.number_suffix_length', 4)));
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
