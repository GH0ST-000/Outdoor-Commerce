<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Media;

use App\Domains\Catalog\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Polling payload for the admin upload UI. Exposes the failure code and the safe
 * operator message, never the underlying exception text or storage location.
 *
 * @mixin MediaAsset
 */
final class MediaAssetStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MediaAsset $asset */
        $asset = $this->resource;

        return [
            'id' => $asset->id,
            'status' => $asset->status->value,
            'original_filename' => $asset->original_filename,
            'mime_type' => $asset->mime_type,
            'byte_size' => $asset->byte_size,
            'width' => $asset->width,
            'height' => $asset->height,
            'attempts' => $asset->attempts,
            'failure_code' => $asset->failure_code,
            'failure_message' => $asset->failure_message,
            'derivative_count' => $asset->relationLoaded('derivatives')
                ? $asset->derivatives->count()
                : $asset->derivatives()->count(),
            'processed_at' => $asset->processed_at?->toIso8601String(),
            'created_at' => $asset->created_at?->toIso8601String(),
            'updated_at' => $asset->updated_at?->toIso8601String(),
        ];
    }
}
