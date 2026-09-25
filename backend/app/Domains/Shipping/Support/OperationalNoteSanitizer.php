<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Support;

final class OperationalNoteSanitizer
{
    public function sanitize(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $trimmed = trim($note);
        if ($trimmed === '') {
            return null;
        }

        $max = max(1, (int) config('shipping.note_max_length', 1000));
        if (strlen($trimmed) > $max) {
            $trimmed = substr($trimmed, 0, $max);
        }

        if (preg_match('/<[^>]+>/', $trimmed) === 1) {
            $trimmed = trim(strip_tags($trimmed));
        }

        return $trimmed === '' ? null : $trimmed;
    }
}
