<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Catalog\Actions\Media\UploadMediaAction;
use App\Domains\Catalog\Models\MediaAttachment;
use App\Domains\Hunting\Enums\SpeciesMediaRole;
use App\Domains\Hunting\Events\SpeciesChanged;
use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Models\SpeciesMediaAttribution;
use App\Domains\Hunting\Support\SpeciesUrlValidator;
use App\Domains\Identity\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

final class SpeciesMediaService
{
    public function __construct(private readonly UploadMediaAction $upload) {}

    /**
     * @param  list<UploadedFile>  $files
     * @param  array<string, mixed>  $meta
     * @return Collection<int, MediaAttachment>
     */
    public function attach(
        Species $species,
        array $files,
        User $actor,
        array $meta,
        ?string $requestId,
        ?string $ip,
        ?string $ua,
    ): Collection {
        $role = SpeciesMediaRole::from((string) ($meta['role'] ?? SpeciesMediaRole::Identification->value));
        $sourceUrl = isset($meta['source_url']) ? trim((string) $meta['source_url']) : null;
        if ($sourceUrl !== null && $sourceUrl !== '' && ! SpeciesUrlValidator::isSafe($sourceUrl)) {
            throw SpeciesException::sourceUrlInvalid();
        }

        $license = isset($meta['license']) ? trim((string) $meta['license']) : '';
        if ($license === '') {
            throw SpeciesException::publicationInvalid(['license' => 'A license is required for species media.']);
        }

        $attachments = $this->upload->execute(
            $species,
            $files,
            $actor,
            $role->attachmentRole(),
            $requestId,
            $ip,
            $ua,
        );

        $makePrimary = (bool) ($meta['is_primary'] ?? false);
        foreach ($attachments as $attachment) {
            SpeciesMediaAttribution::query()->updateOrCreate(
                ['media_attachment_id' => $attachment->id],
                [
                    'photographer_or_creator' => $meta['photographer_or_creator'] ?? null,
                    'license' => $license,
                    'source_url' => $sourceUrl !== '' ? $sourceUrl : null,
                    'locale' => $meta['locale'] ?? null,
                ],
            );

            if ($makePrimary) {
                MediaAttachment::query()
                    ->where('mediable_type', 'species')
                    ->where('mediable_id', $species->id)
                    ->update(['is_primary' => false]);
                $attachment->is_primary = true;
                $attachment->save();
                $makePrimary = false;
            }
        }

        event(new SpeciesChanged($species->id, $species->public_id, 'media'));

        return $attachments;
    }
}
