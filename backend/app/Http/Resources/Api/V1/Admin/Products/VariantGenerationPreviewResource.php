<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class VariantGenerationPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $preview */
        $preview = $this->resource;

        return $preview;
    }
}
