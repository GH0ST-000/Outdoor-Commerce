<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Actions\Media;

use App\Domains\Catalog\DTOs\Media\MediaMetadataData;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Catalog\Services\CatalogCache;
use App\Domains\Catalog\Support\CatalogLocales;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Updates alt text, caption, and focal point. Alt text is accessibility-critical,
 * so it is stored per locale with the same fallback rules as the rest of the
 * catalog rather than as a single untranslated string.
 */
final class UpdateMediaMetadataAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly CatalogCache $catalogCache,
    ) {}

    public function execute(
        MediaAttachment $attachment,
        MediaMetadataData $data,
        Authenticatable $actor,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): MediaAttachment {
        $this->assertTranslations($data);
        $this->assertFocalPoint($data);

        $updated = DB::transaction(function () use ($attachment, $data, $actor, $requestId, $ipAddress, $userAgent): MediaAttachment {
            $attachment->loadMissing('translations');

            $old = [
                'focal_point_x' => $attachment->focal_point_x,
                'focal_point_y' => $attachment->focal_point_y,
                'locales' => $attachment->translations->pluck('locale')->all(),
            ];

            if ($data->focalPointProvided) {
                $attachment->focal_point_x = $data->focalPointX !== null ? (string) $data->focalPointX : null;
                $attachment->focal_point_y = $data->focalPointY !== null ? (string) $data->focalPointY : null;
            }

            $attachment->updated_by = (int) $actor->getAuthIdentifier();
            $attachment->save();

            foreach ($data->translations ?? [] as $locale => $values) {
                $altProvided = array_key_exists('alt_text', $values);
                $captionProvided = array_key_exists('caption', $values);

                $existing = $attachment->translations()->where('locale', $locale)->first();

                $payload = [
                    'alt_text' => $altProvided
                        ? $this->normalize($values['alt_text'] ?? null, (int) config('media.metadata.alt_text_max_length', 300))
                        : $existing?->alt_text,
                    'caption' => $captionProvided
                        ? $this->normalize($values['caption'] ?? null, (int) config('media.metadata.caption_max_length', 600))
                        : $existing?->caption,
                ];

                if ($payload['alt_text'] === null && $payload['caption'] === null) {
                    $existing?->delete();

                    continue;
                }

                $attachment->translations()->updateOrCreate(['locale' => $locale], $payload);
            }

            $attachment->unsetRelation('translations')->load('translations');

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::MediaMetadataUpdated,
                actorUserId: (int) $actor->getAuthIdentifier(),
                subjectType: 'media_attachment',
                subjectId: (string) $attachment->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                oldValues: $old,
                newValues: [
                    'focal_point_x' => $attachment->focal_point_x,
                    'focal_point_y' => $attachment->focal_point_y,
                    'locales' => $attachment->translations->pluck('locale')->all(),
                ],
            ));

            return $attachment;
        });

        $this->catalogCache->bump();

        return $updated->load(['asset.derivatives', 'translations']);
    }

    private function assertTranslations(MediaMetadataData $data): void
    {
        foreach ($data->translations ?? [] as $locale => $_values) {
            if (! CatalogLocales::isSupported((string) $locale)) {
                throw ValidationException::withMessages([
                    "translations.{$locale}" => ['Unsupported locale.'],
                ]);
            }
        }
    }

    /**
     * A focal point is a pair of normalized coordinates or nothing at all; one axis
     * alone would silently centre the other.
     */
    private function assertFocalPoint(MediaMetadataData $data): void
    {
        if (! $data->focalPointProvided) {
            return;
        }

        $x = $data->focalPointX;
        $y = $data->focalPointY;

        if (($x === null) !== ($y === null)) {
            throw ValidationException::withMessages([
                'focal_point' => ['Both focal point coordinates are required.'],
            ]);
        }

        foreach (['focal_point_x' => $x, 'focal_point_y' => $y] as $key => $value) {
            if ($value !== null && ($value < 0.0 || $value > 1.0)) {
                throw ValidationException::withMessages([
                    $key => ['The focal point must be between 0 and 1.'],
                ]);
            }
        }
    }

    private function normalize(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : Str::limit($trimmed, $maxLength, '');
    }
}
