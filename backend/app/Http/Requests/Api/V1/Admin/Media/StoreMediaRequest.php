<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Coarse shape validation only. The real gatekeeping (content sniffing, dimension
 * limits, gallery quotas) lives in MediaUploadValidator so the worker can reapply
 * exactly the same rules to bytes already on disk.
 */
final class StoreMediaRequest extends FormRequest
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
