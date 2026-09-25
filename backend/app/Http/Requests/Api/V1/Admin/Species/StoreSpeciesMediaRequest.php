<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Species;

use App\Domains\Hunting\Enums\SpeciesMediaRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

final class StoreSpeciesMediaRequest extends FormRequest
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
        $maxKilobytes = (int) config('media.uploads.max_file_size_kilobytes', 12288);
        $maxFiles = (int) config('media.uploads.max_files_per_request', 10);

        return [
            'files' => ['required', 'array', 'min:1', "max:{$maxFiles}"],
            'files.*' => ['required', 'file', "max:{$maxKilobytes}"],
            'role' => ['sometimes', Rule::enum(SpeciesMediaRole::class)],
            'locale' => ['nullable', 'in:ka,en'],
            'caption' => ['nullable', 'string', 'max:500'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'photographer_or_creator' => ['nullable', 'string', 'max:191'],
            'license' => ['required', 'string', 'max:191'],
            'source_url' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploadedFiles(): array
    {
        $files = $this->file('files');

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return array_values(array_filter(
            is_array($files) ? $files : [],
            static fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
    }
}
