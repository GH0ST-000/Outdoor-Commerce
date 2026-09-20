<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Media;

use App\Domains\Catalog\Models\MediaAsset;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Resources\Api\V1\Admin\Media\MediaAssetStatusResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Lightweight polling endpoint the upload UI hits while an asset is pending.
 */
final class AdminMediaStatusController
{
    use AuthorizesRequests;

    public function __invoke(Request $request, MediaAsset $asset): MediaAssetStatusResource
    {
        $this->authorize('viewAny', MediaAttachment::class);

        $asset->load('derivatives');

        return (new MediaAssetStatusResource($asset))->additional([
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ]);
    }
}
