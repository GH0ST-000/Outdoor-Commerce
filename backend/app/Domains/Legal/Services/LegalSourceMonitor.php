<?php

declare(strict_types=1);

namespace App\Domains\Legal\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Legal\Enums\ChangeDetectionStatus;
use App\Domains\Legal\Models\LegalChangeDetection;
use App\Domains\Legal\Models\LegalSource;
use App\Domains\Legal\Support\LegalLogger;
use App\Domains\Legal\Support\LegalUrlGuard;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\Http;

final class LegalSourceMonitor
{
    public function __construct(
        private readonly LegalUrlGuard $urls,
        private readonly LegalLogger $logger,
        private readonly LegalAuditRecorder $audit,
        private readonly LegalPublicCache $cache,
    ) {}

    public function check(LegalSource $source, ?User $actor = null): ?LegalChangeDetection
    {
        if (! $source->isPublishableSource() || ! $source->monitor_for_changes || $source->official_base_url === null) {
            return null;
        }

        $url = $this->urls->assertAllowedHttps($source->official_base_url, $source->allowed_domain);
        $timeout = (int) config('legal.retrieval.timeout_seconds', 8);

        try {
            $response = Http::timeout($timeout)->withOptions(['allow_redirects' => false])->head($url);
        } catch (\Throwable $exception) {
            $this->logger->warning('source_monitor_failed', [
                'source_id' => $source->public_id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $etag = (string) $response->header('ETag');
        $modified = (string) $response->header('Last-Modified');
        $length = (int) $response->header('Content-Length');
        $previous = [
            'etag' => $source->last_etag,
            'last_modified' => $source->last_modified_header,
            'content_length' => $source->last_content_length,
        ];
        $current = ['etag' => $etag ?: null, 'last_modified' => $modified ?: null, 'content_length' => $length ?: null];

        $changed = ($etag !== '' && $source->last_etag !== null && $etag !== $source->last_etag)
            || ($modified !== '' && $source->last_modified_header !== null && $modified !== $source->last_modified_header)
            || ($length > 0 && $source->last_content_length !== null && $length !== (int) $source->last_content_length);

        $source->last_checked_at = now();
        if (! $changed) {
            $source->last_etag = $etag ?: $source->last_etag;
            $source->last_modified_header = $modified ?: $source->last_modified_header;
            $source->last_content_length = $length ?: $source->last_content_length;
            $source->save();

            return null;
        }

        $open = LegalChangeDetection::query()
            ->where('legal_source_id', $source->id)
            ->where('status', ChangeDetectionStatus::Open)
            ->first();
        if ($open !== null) {
            $source->save();

            return $open;
        }

        $detection = LegalChangeDetection::query()->create([
            'legal_source_id' => $source->id,
            'status' => ChangeDetectionStatus::Open,
            'previous_metadata' => $previous,
            'current_metadata' => $current,
            'signal' => 'http_metadata',
            'detected_at' => now(),
        ]);
        $source->last_change_detected_at = now();
        $source->last_etag = $etag ?: $source->last_etag;
        $source->last_modified_header = $modified ?: $source->last_modified_header;
        $source->last_content_length = $length ?: $source->last_content_length;
        $source->save();
        $this->logger->info('source_change_detected', ['source_id' => $source->public_id, 'detection_id' => $detection->public_id]);
        if ($actor !== null) {
            $this->audit->record(AuditEvent::LegalChangeDetected, $actor, 'legal_change_detection', $detection->public_id);
        }
        $this->cache->bump();

        return $detection;
    }

    public function confirm(LegalChangeDetection $detection, User $actor, string $notes = ''): LegalChangeDetection
    {
        $detection->status = ChangeDetectionStatus::Confirmed;
        $detection->internal_notes = $notes;
        $detection->reviewed_by = $actor->id;
        $detection->reviewed_at = now();
        $detection->save();
        $this->audit->record(AuditEvent::LegalChangeConfirmed, $actor, 'legal_change_detection', $detection->public_id);

        return $detection;
    }

    public function dismiss(LegalChangeDetection $detection, User $actor, string $notes = ''): LegalChangeDetection
    {
        $detection->status = ChangeDetectionStatus::Dismissed;
        $detection->internal_notes = $notes;
        $detection->reviewed_by = $actor->id;
        $detection->reviewed_at = now();
        $detection->save();
        $this->audit->record(AuditEvent::LegalChangeDismissed, $actor, 'legal_change_detection', $detection->public_id);

        return $detection;
    }
}
