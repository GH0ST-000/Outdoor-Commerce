<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Search;

use App\Http\Support\ApiErrorResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class PublicSearchRequest extends FormRequest
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
        $max = (int) config('search.max_query_length', 100);

        return [
            'q' => ['required', 'string', 'min:1', 'max:'.$max],
            'locale' => ['sometimes', 'string', 'max:8'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('search.grouped_limit', 10)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->query->has('q')) {
            $this->merge(['q' => trim((string) $this->query('q'))]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ApiErrorResponse::make(
            $this,
            'SEARCH_QUERY_INVALID',
            'The search query is invalid.',
            422,
            $validator->errors()->toArray(),
        ));
    }
}
