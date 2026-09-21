<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Media;

use App\Domains\Catalog\Actions\Media\RemoveMediaAttachmentAction;
use App\Domains\Catalog\Actions\Media\ReorderMediaAction;
use App\Domains\Catalog\Actions\Media\RetryMediaProcessingAction;
use App\Domains\Catalog\Actions\Media\SetPrimaryMediaAction;
use App\Domains\Catalog\Actions\Media\UpdateMediaMetadataAction;
use App\Domains\Catalog\Actions\Media\UploadMediaAction;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\Media\MediaCapabilityService;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Admin\Media\ReorderMediaRequest;
use App\Http\Requests\Api\V1\Admin\Media\StoreMediaRequest;
use App\Http\Requests\Api\V1\Admin\Media\UpdateMediaMetadataRequest;
use App\Http\Resources\Api\V1\Admin\Media\MediaAssetStatusResource;
use App\Http\Resources\Api\V1\Admin\Media\MediaAttachmentResource;
use App\Http\Support\ApiErrorResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Product gallery endpoints. Uploads answer 202: the originals are persisted, but
 * the renditions that make an image usable are produced by the `media` queue.
 */
final class AdminProductMediaController
{
    use AuthorizesRequests;

    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        $this->authorize('viewAny', MediaAttachment::class);

        $attachments = $product->mediaAttachments()
            ->with(['asset.derivatives', 'translations'])
            ->get();

        return MediaAttachmentResource::collection($attachments)
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function store(
        StoreMediaRequest $request,
        Product $product,
        UploadMediaAction $action,
        MediaCapabilityService $capabilities,
    ): JsonResponse {
        $this->authorize('create', MediaAttachment::class);

        if (! $capabilities->supportsGd()) {
            return ApiErrorResponse::make(
                $request,
                'MEDIA_PROCESSING_UNAVAILABLE',
                'Image processing is unavailable on this server.',
                503,
            );
        }

        /** @var User $actor */
        $actor = $request->user();

        $attachments = $action->execute(
            $product,
            $request->uploadedFiles(),
            $actor,
            requestId: $this->requestId($request),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return MediaAttachmentResource::collection($attachments)
            ->additional(['meta' => ['request_id' => $this->requestId($request)]])
            ->response()
            ->setStatusCode(202);
    }

    public function reorder(
        ReorderMediaRequest $request,
        Product $product,
        ReorderMediaAction $action,
    ): AnonymousResourceCollection {
        $this->authorize('reorder', MediaAttachment::class);

        /** @var User $actor */
        $actor = $request->user();

        $attachments = $action->execute(
            $product,
            $request->orderedIds(),
            $actor,
            requestId: $this->requestId($request),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return MediaAttachmentResource::collection($attachments)
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function update(
        UpdateMediaMetadataRequest $request,
        Product $product,
        MediaAttachment $attachment,
        UpdateMediaMetadataAction $action,
    ): MediaAttachmentResource {
        $this->authorize('update', $attachment);
        $this->assertOwnership($product, $attachment);

        /** @var User $actor */
        $actor = $request->user();

        $updated = $action->execute(
            $attachment,
            $request->toData(),
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new MediaAttachmentResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function primary(
        Request $request,
        Product $product,
        MediaAttachment $attachment,
        SetPrimaryMediaAction $action,
    ): MediaAttachmentResource {
        $this->authorize('update', $attachment);
        $this->assertOwnership($product, $attachment);

        /** @var User $actor */
        $actor = $request->user();

        $updated = $action->execute(
            $attachment,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new MediaAttachmentResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function destroy(
        Request $request,
        Product $product,
        MediaAttachment $attachment,
        RemoveMediaAttachmentAction $action,
    ): MediaAttachmentResource {
        $this->authorize('delete', $attachment);
        $this->assertOwnership($product, $attachment);

        /** @var User $actor */
        $actor = $request->user();

        $removed = $action->execute(
            $attachment,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new MediaAttachmentResource($removed))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]]);
    }

    public function retry(
        Request $request,
        Product $product,
        MediaAttachment $attachment,
        RetryMediaProcessingAction $action,
    ): JsonResponse {
        $this->authorize('retry', $attachment);
        $this->assertOwnership($product, $attachment);

        $asset = $attachment->asset;
        abort_if($asset === null, 404);

        /** @var User $actor */
        $actor = $request->user();

        $updated = $action->execute(
            $asset,
            $actor,
            $this->requestId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return (new MediaAssetStatusResource($updated))
            ->additional(['meta' => ['request_id' => $this->requestId($request)]])
            ->response()
            ->setStatusCode(202);
    }

    /**
     * A nested route must address media that actually belongs to the parent;
     * anything else is indistinguishable from a wrong URL, so it answers 404.
     */
    private function assertOwnership(Product $product, MediaAttachment $attachment): void
    {
        abort_unless(
            $attachment->mediable_type === $product->getMorphClass()
                && $attachment->mediable_id === (int) $product->id,
            404,
        );
    }

    private function requestId(Request $request): ?string
    {
        $id = $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
