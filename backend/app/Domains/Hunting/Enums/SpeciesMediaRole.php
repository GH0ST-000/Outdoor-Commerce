<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

use App\Domains\Catalog\Enums\MediaAttachmentRole;

enum SpeciesMediaRole: string
{
    case Identification = 'identification';
    case Male = 'male';
    case Female = 'female';
    case Juvenile = 'juvenile';
    case Habitat = 'habitat';
    case Illustration = 'illustration';
    case Audio = 'audio';
    case Gallery = 'gallery';

    public function attachmentRole(): MediaAttachmentRole
    {
        return match ($this) {
            self::Identification => MediaAttachmentRole::Identification,
            self::Male => MediaAttachmentRole::Male,
            self::Female => MediaAttachmentRole::Female,
            self::Juvenile => MediaAttachmentRole::Juvenile,
            self::Habitat => MediaAttachmentRole::Habitat,
            self::Illustration => MediaAttachmentRole::Illustration,
            self::Audio => MediaAttachmentRole::Audio,
            self::Gallery => MediaAttachmentRole::Gallery,
        };
    }
}
