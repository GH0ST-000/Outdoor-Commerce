<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Inventory;

use App\Http\Support\RequiresIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;

final class ReleaseInventoryReservationRequest extends FormRequest
{
    use RequiresIdempotencyKey;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['release_reason' => ['nullable', 'string', 'max:64']];
    }
}
