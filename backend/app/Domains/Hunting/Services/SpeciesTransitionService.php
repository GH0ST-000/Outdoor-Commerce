<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Services;

use App\Domains\Hunting\Enums\SpeciesPublicationStatus;
use App\Domains\Hunting\Events\SpeciesArchived;
use App\Domains\Hunting\Events\SpeciesChanged;
use App\Domains\Hunting\Events\SpeciesPublished;
use App\Domains\Hunting\Events\SpeciesUnpublished;
use App\Domains\Hunting\Exceptions\SpeciesException;
use App\Domains\Hunting\Models\Species;
use App\Domains\Hunting\Support\SpeciesLogger;
use App\Domains\Identity\Models\User;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;

final class SpeciesTransitionService
{
    public function __construct(
        private readonly SpeciesPublicationStateMachine $machine,
        private readonly SpeciesPublishingValidator $validator,
        private readonly SpeciesRevisionService $revisions,
        private readonly RecordAuditEventAction $audit,
        private readonly SpeciesLogger $logger,
    ) {}

    public function submitReview(Species $species, User $actor, ?string $requestId, ?string $ip, ?string $ua): Species
    {
        return $this->transition(
            $species,
            SpeciesPublicationStatus::InReview,
            $actor,
            'Submitted for review.',
            AuditEvent::SpeciesSubmittedReview,
            $requestId,
            $ip,
            $ua,
        );
    }

    public function unpublish(Species $species, User $actor, string $reason, ?string $requestId, ?string $ip, ?string $ua): Species
    {
        if (trim($reason) === '') {
            throw SpeciesException::publicationInvalid(['reason' => 'A reason is required.']);
        }

        $updated = $this->transition(
            $species,
            SpeciesPublicationStatus::InReview,
            $actor,
            'Unpublished: '.$reason,
            AuditEvent::SpeciesUnpublished,
            $requestId,
            $ip,
            $ua,
        );

        event(new SpeciesUnpublished($updated->id, $updated->public_id, $reason));

        return $updated;
    }

    public function archive(Species $species, User $actor, string $reason, ?string $requestId, ?string $ip, ?string $ua): Species
    {
        if (trim($reason) === '') {
            throw SpeciesException::publicationInvalid(['reason' => 'A reason is required.']);
        }

        $updated = $this->transition(
            $species,
            SpeciesPublicationStatus::Archived,
            $actor,
            'Archived: '.$reason,
            AuditEvent::SpeciesArchived,
            $requestId,
            $ip,
            $ua,
            extra: ['archived_at' => now()],
        );

        event(new SpeciesArchived($updated->id, $updated->public_id, $reason));

        return $updated;
    }

    public function returnToDraft(Species $species, User $actor, ?string $requestId, ?string $ip, ?string $ua): Species
    {
        return $this->transition(
            $species,
            SpeciesPublicationStatus::Draft,
            $actor,
            'Returned to draft.',
            AuditEvent::SpeciesReturnedToDraft,
            $requestId,
            $ip,
            $ua,
        );
    }

    public function publish(Species $species, User $actor, ?string $requestId, ?string $ip, ?string $ua): Species
    {
        $issues = $this->validator->issues($species);
        if ($issues !== []) {
            throw SpeciesException::publicationInvalid(['issues' => $issues]);
        }

        if ((bool) config('species.publishing.require_distinct_reviewer', false)
            && $species->updated_by === $actor->id
            && $species->created_by === $actor->id) {
            throw SpeciesException::permissionRequired();
        }

        $updated = $this->transition(
            $species,
            SpeciesPublicationStatus::Published,
            $actor,
            'Published species.',
            AuditEvent::SpeciesPublished,
            $requestId,
            $ip,
            $ua,
            extra: [
                'published_at' => $species->published_at ?? now(),
                'reviewed_at' => now(),
                'reviewed_by' => $actor->id,
            ],
        );

        $this->logger->info('published', [
            'species_public_id' => $updated->public_id,
            'content_version' => $updated->content_version,
        ]);

        event(new SpeciesPublished($updated->id, $updated->public_id, $updated->content_version));

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transition(
        Species $species,
        SpeciesPublicationStatus $to,
        User $actor,
        string $summary,
        AuditEvent $event,
        ?string $requestId,
        ?string $ip,
        ?string $ua,
        array $extra = [],
    ): Species {
        return DB::transaction(function () use ($species, $to, $actor, $summary, $event, $requestId, $ip, $ua, $extra): Species {
            $locked = Species::query()->whereKey($species->id)->lockForUpdate()->firstOrFail();
            $from = $locked->publication_status;
            $this->machine->assertTransition($from, $to);

            $locked->publication_status = $to;
            foreach ($extra as $key => $value) {
                $locked->{$key} = $value;
            }
            if ($to === SpeciesPublicationStatus::Draft) {
                $locked->archived_at = null;
            }
            $locked->updated_by = $actor->id;
            $locked->content_version = $locked->content_version + 1;
            $locked->save();

            $this->revisions->record($locked, $actor, $summary);
            $this->audit->execute(new AuditEventData(
                event: $event,
                actorUserId: $actor->id,
                subjectType: 'species',
                subjectId: $locked->public_id,
                requestId: $requestId,
                ipAddress: $ip,
                userAgent: $ua,
                oldValues: ['publication_status' => $from->value],
                newValues: ['publication_status' => $to->value],
            ));

            event(new SpeciesChanged($locked->id, $locked->public_id, $to->value));

            return $locked->fresh() ?? $locked;
        });
    }
}
