<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Media;

use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Services\Media\MediaPresentationService;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin view of one attachment.
 *
 * The payload is produced by MediaPresentationService, which only ever emits
 * derivative URLs — `original_disk` and `original_path` are not part of the
 * serialized shape at any nesting level.
 *
 * @mixin MediaAttachment
 */
final class MediaAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MediaAttachment $attachment */
        $attachment = $this->resource;

        $locale = CatalogLocales::isSupported((string) $request->query('locale', ''))
            ? (string) $request->query('locale')
            : CatalogLocales::default();

        return app(MediaPresentationService::class)->manifest($attachment, $locale);
    }
}
