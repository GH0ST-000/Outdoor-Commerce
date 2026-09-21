<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Enums;

/**
 * Derivative bounding boxes. Values are config-driven so sizes can change
 * without a migration; existing derivatives are replaced on reprocess.
 */
enum MediaPreset: string
{
    case Thumbnail = 'thumbnail';
    case Card = 'card';
    case CardLarge = 'card_large';
    case Detail = 'detail';
    case Zoom = 'zoom';

    public function maxWidth(): int
    {
        return (int) config('media.presets.'.$this->value.'.max_width', 400);
    }

    public function maxHeight(): int
    {
        return (int) config('media.presets.'.$this->value.'.max_height', 400);
    }

    /**
     * Ordered smallest-to-largest, which is also the srcset order.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Thumbnail, self::Card, self::CardLarge, self::Detail, self::Zoom];
    }
}
