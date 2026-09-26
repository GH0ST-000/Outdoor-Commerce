<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Spatial;

use App\Domains\Geography\Enums\SpatialAssignmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignSpatialRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'legal_rule_id' => ['required', 'uuid'],
            'assignment_type' => ['required', Rule::enum(SpatialAssignmentType::class)],
            'precedence' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'zone_geometry_version_id' => ['nullable', 'uuid'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
