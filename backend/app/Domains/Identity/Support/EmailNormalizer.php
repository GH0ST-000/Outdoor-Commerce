<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

/**
 * Canonical email form used for storage, lookups, and rate-limit keys.
 *
 * Only case and surrounding whitespace are normalized. Provider specific
 * tricks (dot stripping, plus-addressing) are deliberately preserved because
 * two addresses that differ there are legitimately different mailboxes.
 */
final class EmailNormalizer
{
    public static function normalize(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }
}
