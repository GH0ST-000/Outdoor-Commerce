<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Spatial;

use App\Domains\Geography\Enums\SpatialDatasetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSpatialDatasetRequest extends FormRequest
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
            'spatial_source_id' => [$required, 'uuid'],
            'name' => [$required, 'string', 'max:191'],
            'dataset_type' => [$required, Rule::enum(SpatialDatasetType::class)],
            'jurisdiction_code' => ['nullable', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:2000'],
            'native_crs' => ['nullable', 'string', 'max:64'],
            'update_frequency' => ['nullable', 'string', 'max:64'],
            'is_fictional' => ['sometimes', 'boolean'],
        ];
    }
}
