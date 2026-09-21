<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Enums;

/**
 * Day 9 ships a single role. Future roles (size chart, manual, lifestyle) slot in
 * here without touching the attachment schema.
 */
enum MediaAttachmentRole: string
{
    case Gallery = 'gallery';
}
