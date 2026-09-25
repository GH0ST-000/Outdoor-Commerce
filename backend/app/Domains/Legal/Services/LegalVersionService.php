<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\ExtractionStatus;
use App\Domains\Legal\Enums\LegalReviewStatus;
use App\Domains\Legal\Enums\LegalVerificationStatus;
use App\Domains\Legal\Exceptions\LegalException;
use App\Domains\Legal\Models\LegalDocument;
use App\Domains\Legal\Models\LegalDocumentVersion;
use App\Domains\Legal\Models\LegalProvision;
use App\Domains\Legal\Support\LegalUrlGuard;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

final class LegalVersionService
{
    public function __construct(
        private readonly LegalFileIngestionService $files,
        private readonly LegalUrlGuard $urls,
        private readonly LegalRuleStateMachine $states,
        private readonly LegalPublicCache $cache,
        private readonly LegalAuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(LegalDocument $document, array $input, User $actor, ?UploadedFile $file = null): LegalDocumentVersion
    {
        return DB::transaction(function () use ($document, $input, $actor, $file): LegalDocumentVersion {
            $stored = null;
            if ($file !== null) {
                $stored = $this->files->storeUpload($file);
            } elseif (! empty($input['retrieve_from_url']) && (bool) config('legal.retrieval.enabled', true)) {
                $stored = $this->retrieve($document, (string) $input['source_url'], $actor);
            } elseif (! empty($input['content_checksum'])) {
                $stored = [
                    'disk' => null,
                    'path' => null,
                    'checksum' => (string) $input['content_checksum'],
                    'mime' => $input['mime_type'] ?? null,
                    'size' => $input['file_size'] ?? null,
                    'original' => $input['original_filename'] ?? null,
                ];
            } else {
                throw LegalException::fileRejected('A file upload, official retrieval, or checksum is required.');
            }

            $existing = LegalDocumentVersion::query()
                ->where('legal_document_id', $document->id)
                ->where('content_checksum', $stored['checksum'])
                ->first();
            if ($existing !== null) {
                throw LegalException::checksumConflict();
            }

            $version = LegalDocumentVersion::query()->create([
                'legal_document_id' => $document->id,
                'version_label' => $input['version_label'],
                'source_url' => $input['source_url'] ?? $document->official_url,
                'source_published_at' => $input['source_published_at'] ?? null,
                'effective_from' => $input['effective_from'] ?? now(),
                'effective_until' => $input['effective_until'] ?? null,
                'retrieved_at' => now(),
                'retrieved_by' => $actor->id,
                'content_checksum' => $stored['checksum'],
                'checksum_algorithm' => 'sha256',
                'mime_type' => $stored['mime'],
                'file_size' => $stored['size'],
                'storage_disk' => $stored['disk'],
                'storage_path' => $stored['path'],
                'original_filename' => $stored['original'],
                'extraction_status' => ExtractionStatus::ManualOnly,
                'verification_status' => LegalVerificationStatus::Unverified,
                'review_status' => LegalReviewStatus::Draft,
                'change_summary' => $input['change_summary'] ?? null,
                'supersedes_version_id' => isset($input['supersedes_version_id'])
                    ? LegalDocumentVersion::query()->where('public_id', $input['supersedes_version_id'])->value('id')
                    : $document->current_version_id,
            ]);

            $this->audit->record(AuditEvent::LegalVersionCreated, $actor, 'legal_document_version', $version->public_id, null, [
                'checksum' => $version->content_checksum,
            ]);
            $this->cache->bump();

            return $version;
        });
    }

    public function submitReview(LegalDocumentVersion $version, User $actor): LegalDocumentVersion
    {
        $this->states->assertVersionTransition($version->review_status, LegalReviewStatus::InReview);
        $version->review_status = LegalReviewStatus::InReview;
        $version->save();
        $this->audit->record(AuditEvent::LegalVersionSubmittedReview, $actor, 'legal_document_version', $version->public_id);

        return $version;
    }

    public function approve(LegalDocumentVersion $version, User $actor): LegalDocumentVersion
    {
        return DB::transaction(function () use ($version, $actor): LegalDocumentVersion {
            $locked = LegalDocumentVersion::query()->lockForUpdate()->findOrFail($version->id);
            $this->states->assertVersionTransition($locked->review_status, LegalReviewStatus::Approved);
            $locked->review_status = LegalReviewStatus::Approved;
            $locked->verification_status = LegalVerificationStatus::Verified;
            $locked->reviewed_at = now();
            $locked->reviewed_by = $actor->id;
            $locked->save();

            $document = LegalDocument::query()->lockForUpdate()->findOrFail($locked->legal_document_id);
            if ($document->current_version_id !== null && $document->current_version_id !== $locked->id) {
                $previous = LegalDocumentVersion::query()->find($document->current_version_id);
                if ($previous !== null && $previous->review_status === LegalReviewStatus::Approved) {
                    $previous->review_status = LegalReviewStatus::Superseded;
                    $previous->save();
                }
            }
            $document->current_version_id = $locked->id;
            $document->save();
            LegalProvision::query()
                ->where('legal_document_version_id', $locked->id)
                ->where('review_status', LegalReviewStatus::Draft)
                ->update([
                    'review_status' => LegalReviewStatus::Approved,
                    'reviewed_at' => now(),
                    'reviewed_by' => $actor->id,
                ]);

            $this->audit->record(AuditEvent::LegalVersionApproved, $actor, 'legal_document_version', $locked->public_id);
            $this->cache->bump();

            return $locked;
        });
    }

    public function reject(LegalDocumentVersion $version, User $actor, string $reason): LegalDocumentVersion
    {
        $this->states->assertVersionTransition($version->review_status, LegalReviewStatus::Rejected);
        $version->review_status = LegalReviewStatus::Rejected;
        $version->reviewed_at = now();
        $version->reviewed_by = $actor->id;
        $version->change_summary = $reason;
        $version->save();
        $this->audit->record(AuditEvent::LegalVersionRejected, $actor, 'legal_document_version', $version->public_id, null, ['reason' => $reason]);

        return $version;
    }

    public function assertCanDownload(LegalDocumentVersion $version): void
    {
        if ($version->storage_disk === null || $version->storage_path === null) {
            throw LegalException::notFound('Legal file');
        }
        if (str_contains($version->storage_path, '..')) {
            throw LegalException::fileRejected('Invalid storage path.');
        }
        if (! Storage::disk($version->storage_disk)->exists($version->storage_path)) {
            throw LegalException::notFound('Legal file');
        }
    }

    /**
     * @return array{disk: string, path: string, checksum: string, mime: string, size: int, original: string}
     */
    private function retrieve(LegalDocument $document, string $url, User $actor): array
    {
        $document->loadMissing('source');
        $safe = $this->urls->assertAllowedHttps($url, $document->source?->allowed_domain);
        $timeout = (int) config('legal.retrieval.timeout_seconds', 8);
        $max = (int) config('legal.retrieval.max_bytes', 20 * 1024 * 1024);
        $response = Http::timeout($timeout)
            ->withOptions([
                'allow_redirects' => [
                    'max' => (int) config('legal.retrieval.max_redirects', 3),
                    'strict' => true,
                    'protocols' => ['https'],
                ],
            ])
            ->withHeaders(['Accept' => 'application/pdf,text/plain,text/html'])
            ->get($safe);
        if (! $response->successful()) {
            throw LegalException::urlRejected('The official document could not be retrieved.');
        }
        $body = $response->body();
        if (strlen($body) > $max) {
            throw LegalException::fileRejected('The remote file exceeds the size limit.');
        }
        $mime = (string) $response->header('Content-Type');
        $mime = strtolower(trim(explode(';', $mime)[0] ?? ''));
        $allowed = config('legal.storage.allowed_mime_types', []);
        if (! in_array($mime, $allowed, true)) {
            throw LegalException::fileRejected('The remote MIME type is not allowed.');
        }
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'text/html' => 'html',
            default => 'txt',
        };

        return $this->files->storeBytes($body, $mime, $extension);
    }
}
