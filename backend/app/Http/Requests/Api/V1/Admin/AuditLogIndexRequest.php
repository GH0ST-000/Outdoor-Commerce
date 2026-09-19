<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AuditLogIndexRequest extends FormRequest
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
            'event' => ['sometimes', 'nullable', Rule::enum(AuditEvent::class)],
            'actor_user_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'subject_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'subject_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'sort' => ['sometimes', 'nullable', Rule::in(['created_at', 'event', 'actor_user_id'])],
            'direction' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
