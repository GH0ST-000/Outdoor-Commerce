<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Catalog\Enums\MediaStatus;
use App\Domains\Hunting\Enums\SpeciesVerificationStatus;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Support\SpeciesLocales;
use App\Domains\Hunting\Support\SpeciesSlug;

final class SpeciesPublishingValidator
{
    /**
     * @return list<array{code: string, message: string}>
     */
    public function issues(Species $species): array
    {
        $species->loadMissing([
            'translations',
            'citations',
            'mediaAttachments.asset',
            'characteristic',
        ]);

        $issues = [];

        if (! SpeciesSlug::isValid($species->canonical_slug)) {
            $issues[] = ['code' => 'SPECIES_SLUG_CONFLICT', 'message' => 'Canonical slug is invalid.'];
        }

        if (trim($species->scientific_name) === '') {
            $issues[] = ['code' => 'SPECIES_TAXONOMY_INVALID', 'message' => 'Scientific name is required.'];
        }

        $ka = $species->translation(SpeciesLocales::default());
        if ($ka === null || ! $ka->isPublished() || trim($ka->common_name) === '' || trim($ka->summary) === '') {
            $issues[] = [
                'code' => 'SPECIES_TRANSLATION_REQUIRED',
                'message' => 'A published Georgian common name and summary are required.',
            ];
        }

        if ($species->citations->isEmpty()) {
            $issues[] = ['code' => 'SPECIES_SOURCE_REQUIRED', 'message' => 'At least one source citation is required.'];
        }

        $hasPrimary = $species->mediaAttachments
            ->contains(static fn ($attachment): bool => $attachment->is_primary
                && $attachment->asset?->status === MediaStatus::Ready);

        if (! $hasPrimary && ! $species->no_media_required) {
            $issues[] = [
                'code' => 'SPECIES_MEDIA_REQUIRED',
                'message' => 'A ready primary image is required, or mark the record as deliberately without media.',
            ];
        }

        $minimum = SpeciesVerificationStatus::from(
            (string) config('species.publishing.minimum_verification', 'partially_verified'),
        );
        if ($species->verification_status === SpeciesVerificationStatus::Unverified
            || ($minimum === SpeciesVerificationStatus::Verified
                && $species->verification_status !== SpeciesVerificationStatus::Verified)) {
            $issues[] = [
                'code' => 'SPECIES_PUBLICATION_INVALID',
                'message' => 'Verification status is not sufficient for publication.',
            ];
        }

        if ($species->characteristic !== null) {
            $char = $species->characteristic;
            if ($char->average_length_min !== null && $char->average_length_max !== null
                && (float) $char->average_length_min > (float) $char->average_length_max) {
                $issues[] = ['code' => 'SPECIES_PUBLICATION_INVALID', 'message' => 'Length minimum cannot exceed maximum.'];
            }
            if ($char->average_weight_min !== null && $char->average_weight_max !== null
                && (float) $char->average_weight_min > (float) $char->average_weight_max) {
                $issues[] = ['code' => 'SPECIES_PUBLICATION_INVALID', 'message' => 'Weight minimum cannot exceed maximum.'];
            }
        }

        return $issues;
    }

    public function canPublish(Species $species): bool
    {
        return $this->issues($species) === [];
    }
}
