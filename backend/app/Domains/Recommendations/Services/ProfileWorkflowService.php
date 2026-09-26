<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Recommendations\Enums\RecommendationPlacement;
use App\Domains\Recommendations\Enums\RecommendationProfileStatus;
use App\Domains\Recommendations\Exceptions\RecommendationException;
use App\Domains\Recommendations\Models\RecommendationProfile;
use App\Domains\Recommendations\Models\RecommendationProfileWeight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProfileWorkflowService
{
    public function __construct(
        private readonly ProfileConfigurationValidator $validator,
        private readonly RecommendationAuditRecorder $audit,
        private readonly RecommendationCache $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $configuration
     * @param  array<string, int>  $weights
     */
    public function create(User $actor, string $name, RecommendationPlacement $placement, array $configuration, array $weights): RecommendationProfile
    {
        $configuration = $this->validator->validate($configuration, $weights);

        return DB::transaction(function () use ($actor, $name, $placement, $configuration, $weights): RecommendationProfile {
            $version = (int) RecommendationProfile::query()->where('placement', $placement)->max('version') + 1;
            $profile = RecommendationProfile::query()->create([
                'public_id' => (string) Str::uuid(),
                'name' => $name,
                'slug' => Str::slug($name).'-'.$placement->value.'-v'.$version,
                'placement' => $placement,
                'version' => $version,
                'status' => RecommendationProfileStatus::Draft,
                'minimum_score' => $configuration['minimum_score'],
                'maximum_results' => $configuration['maximum_results'],
                'candidate_limit' => $configuration['candidate_limit'],
                'configuration' => $configuration,
                'effective_from' => now(),
                'created_by' => $actor->id,
            ]);
            foreach ($weights as $dimension => $weight) {
                RecommendationProfileWeight::query()->create([
                    'recommendation_profile_id' => $profile->id,
                    'dimension' => $dimension,
                    'weight' => $weight,
                ]);
            }
            $this->audit->record(AuditEvent::RecommendationProfileCreated, $actor, 'recommendation_profile', $profile->public_id, null, [
                'placement' => $placement->value,
                'version' => $version,
                'weights' => $weights,
            ]);

            return $profile->load('weights');
        });
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @param  array<string, int>  $weights
     */
    public function update(User $actor, RecommendationProfile $profile, array $configuration, array $weights, string $name): RecommendationProfile
    {
        if ($profile->status !== RecommendationProfileStatus::Draft) {
            throw RecommendationException::conflict('Only a draft profile can be edited.');
        }
        $configuration = $this->validator->validate($configuration, $weights);
        $old = $profile->weightMap();

        return DB::transaction(function () use ($actor, $profile, $configuration, $weights, $name, $old): RecommendationProfile {
            $profile->update([
                'name' => $name,
                'minimum_score' => $configuration['minimum_score'],
                'maximum_results' => $configuration['maximum_results'],
                'candidate_limit' => $configuration['candidate_limit'],
                'configuration' => $configuration,
            ]);
            $profile->weights()->delete();
            foreach ($weights as $dimension => $weight) {
                RecommendationProfileWeight::query()->create([
                    'recommendation_profile_id' => $profile->id,
                    'dimension' => $dimension,
                    'weight' => $weight,
                ]);
            }
            $this->audit->record(AuditEvent::RecommendationProfileUpdated, $actor, 'recommendation_profile', $profile->public_id, ['weights' => $old], ['weights' => $weights, 'version' => $profile->version]);

            return $profile->fresh('weights') ?? $profile;
        });
    }

    public function submit(User $actor, RecommendationProfile $profile): RecommendationProfile
    {
        $this->assertStatus($profile, RecommendationProfileStatus::Draft);
        $profile->update(['status' => RecommendationProfileStatus::InReview]);
        $this->audit->record(AuditEvent::RecommendationProfileSubmitted, $actor, 'recommendation_profile', $profile->public_id, null, ['version' => $profile->version]);

        return $profile;
    }

    public function approve(User $actor, RecommendationProfile $profile): RecommendationProfile
    {
        $this->assertStatus($profile, RecommendationProfileStatus::InReview);
        if ($profile->created_by !== null && (int) $profile->created_by === $actor->id) {
            throw RecommendationException::conflict('The profile author cannot approve their own profile.');
        }
        $profile->update([
            'status' => RecommendationProfileStatus::Approved,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);
        $this->audit->record(AuditEvent::RecommendationProfileApproved, $actor, 'recommendation_profile', $profile->public_id, null, ['version' => $profile->version]);

        return $profile;
    }

    public function publish(User $actor, RecommendationProfile $profile): RecommendationProfile
    {
        $this->assertStatus($profile, RecommendationProfileStatus::Approved);
        if ($profile->created_by !== null && (int) $profile->created_by === $actor->id) {
            throw RecommendationException::conflict('The profile author cannot publish their own profile.');
        }

        return DB::transaction(function () use ($actor, $profile): RecommendationProfile {
            RecommendationProfile::query()
                ->where('placement', $profile->placement)
                ->where('status', RecommendationProfileStatus::Published)
                ->where('id', '!=', $profile->id)
                ->update(['status' => RecommendationProfileStatus::Superseded, 'effective_until' => now()]);
            $profile->update([
                'status' => RecommendationProfileStatus::Published,
                'published_by' => $actor->id,
                'published_at' => now(),
                'effective_from' => now(),
            ]);
            $this->cache->bump();
            $this->audit->record(AuditEvent::RecommendationProfilePublished, $actor, 'recommendation_profile', $profile->public_id, null, ['version' => $profile->version, 'placement' => $profile->placement->value]);

            return $profile;
        });
    }

    public function supersede(User $actor, RecommendationProfile $profile): RecommendationProfile
    {
        $this->assertStatus($profile, RecommendationProfileStatus::Published);
        $profile->update([
            'status' => RecommendationProfileStatus::Superseded,
            'effective_until' => now(),
        ]);
        $this->cache->bump();
        $this->audit->record(AuditEvent::RecommendationProfileSuperseded, $actor, 'recommendation_profile', $profile->public_id, null, ['version' => $profile->version]);

        return $profile;
    }

    private function assertStatus(RecommendationProfile $profile, RecommendationProfileStatus $status): void
    {
        if ($profile->status !== $status) {
            throw RecommendationException::conflict('The profile is not in the '.$status->value.' state.');
        }
    }
}
