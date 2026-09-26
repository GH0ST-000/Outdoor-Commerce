<?php

declare(strict_types=1);

return [
    /*
    | Deterministic ranking configuration. Weights live on published profiles.
    | These values are validation bounds, not the live ranking weights.
    */
    'score_min' => 0,
    'score_max' => 100,
    'weight_min' => 0,
    'weight_max' => 40,
    'weight_sum' => 100,
    'activity_only_cap' => 60,
    'merchandising_max_points' => 12,
    'candidate_limit_max' => 200,
    'page_size_max' => 10,
    'default_per_page' => 10,
    'token_ttl_seconds' => 900,
    'cache' => [
        'ttl_seconds' => 45,
        'lock_seconds' => 8,
        'prefix' => 'recommendations',
    ],
    'bulk_queue_threshold' => 25,
    'search_index' => 'recommendation_context',
    'rate_limits' => [
        'public_per_minute' => 30,
        'simulate_per_minute' => 10,
    ],
    'dimensions' => [
        'activity_match',
        'species_exact_match',
        'species_category_match',
        'required_equipment_match',
        'method_match',
        'season_phase_match',
        'region_match',
        'zone_type_match',
        'general_relevance',
        'specification_completeness',
        'availability',
        'popularity',
        'recency',
    ],
    'default_weights' => [
        'activity_match' => 18,
        'species_exact_match' => 22,
        'species_category_match' => 10,
        'required_equipment_match' => 18,
        'method_match' => 8,
        'season_phase_match' => 6,
        'region_match' => 5,
        'zone_type_match' => 5,
        'general_relevance' => 0,
        'specification_completeness' => 4,
        'availability' => 4,
        'popularity' => 0,
        'recency' => 0,
    ],
    'tie_break' => [
        'pinned',
        'pin_priority',
        'final_score',
        'confidence',
        'in_stock',
        'merchandising_priority',
        'published_at',
        'slug',
    ],
];
