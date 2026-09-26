<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Spatial;

use Illuminate\Foundation\Http\FormRequest;

final class UploadSpatialDatasetVersionRequest extends FormRequest
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
        $maxKb = (int) config('spatial.storage.max_file_kilobytes', 20480);

        return [
            'file' => ['required', 'file', 'max:'.$maxKb],
            'version_label' => ['required', 'string', 'max:64'],
            'source_crs' => ['required', 'string', 'max:64'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
        ];
    }
}
