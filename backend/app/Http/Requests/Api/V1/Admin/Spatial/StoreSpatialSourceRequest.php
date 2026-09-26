<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Spatial;

use App\Domains\Geography\Enums\SpatialSourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSpatialSourceRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:191'],
            'source_type' => [$required, Rule::enum(SpatialSourceType::class)],
            'publisher_name' => [$required, 'string', 'max:191'],
            'jurisdiction_code' => ['nullable', 'string', 'max:16'],
            'official_url' => ['nullable', 'url', 'max:2048'],
            'official_identifier' => ['nullable', 'string', 'max:191'],
            'license_name' => ['nullable', 'string', 'max:191'],
            'license_url' => ['nullable', 'url', 'max:2048'],
            'attribution_text' => ['nullable', 'string', 'max:2000'],
            'allowed_usage_notes' => ['nullable', 'string', 'max:2000'],
            'is_fictional' => ['sometimes', 'boolean'],
        ];
    }
}
