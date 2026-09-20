<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Media;

use Illuminate\Foundation\Http\FormRequest;

final class ReorderMediaRequest extends FormRequest
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
            'attachment_ids' => ['required', 'array', 'min:1'],
            'attachment_ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return list<int>
     */
    public function orderedIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('attachment_ids', []);

        return array_map('intval', array_values($ids));
    }
}
