<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Enums;

/**
 * Attachment roles. Product galleries remain `gallery`. Species identification
 * media reuses the same pipeline with dedicated roles.
 */
enum MediaAttachmentRole: string
{
    case Gallery = 'gallery';
    case Identification = 'identification';
    case Male = 'male';
    case Female = 'female';
    case Juvenile = 'juvenile';
    case Habitat = 'habitat';
    case Illustration = 'illustration';
    case Audio = 'audio';
}
