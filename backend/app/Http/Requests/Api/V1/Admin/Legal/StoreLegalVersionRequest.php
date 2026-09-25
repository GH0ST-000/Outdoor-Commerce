<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Legal;

use Illuminate\Foundation\Http\FormRequest;

final class StoreLegalVersionRequest extends FormRequest
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
            'version_label' => ['required', 'string', 'max:64'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'source_published_at' => ['nullable', 'date'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'change_summary' => ['nullable', 'string', 'max:500'],
            'supersedes_version_id' => ['nullable', 'uuid'],
            'retrieve_from_url' => ['sometimes', 'boolean'],
            'content_checksum' => ['nullable', 'string', 'size:64'],
            'mime_type' => ['nullable', 'string', 'max:127'],
            'file_size' => ['nullable', 'integer', 'min:1'],
            'original_filename' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:pdf,txt,html', 'max:20480'],
        ];
    }
}
