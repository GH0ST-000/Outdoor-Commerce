<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Inventory;

use Illuminate\Foundation\Http\FormRequest;

final class AdminInventoryLedgerIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'movement_type' => ['sometimes', 'string'],
            'operation_type' => ['sometimes', 'string'],
            'reference_type' => ['sometimes', 'string'],
            'reference_id' => ['sometimes', 'string'],
            'performed_by' => ['sometimes', 'integer'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
