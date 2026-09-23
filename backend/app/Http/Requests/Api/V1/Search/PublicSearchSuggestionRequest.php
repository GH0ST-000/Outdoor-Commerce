<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Search;

use App\Http\Support\ApiErrorResponse;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

final class PublicSearchSuggestionRequest extends FormRequest
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
            'q' => ['required', 'string', 'max:'.$max],
            'locale' => ['sometimes', 'string', 'max:8'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('search.suggest_limit', 8)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $query = trim((string) $this->input('q', ''));
            if ($query !== '' && mb_strlen($query, 'UTF-8') < (int) config('search.min_suggest_length', 2)) {
                $validator->errors()->add('q', 'The search query is too short.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->query->has('q')) {
            $this->merge(['q' => trim((string) $this->query('q'))]);
        }
    }

    protected function failedValidation(ValidatorContract $validator): void
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
