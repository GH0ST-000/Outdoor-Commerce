<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Media;

use App\Domains\Catalog\DTOs\Media\MediaMetadataData;
use App\Domains\Catalog\Support\CatalogLocales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMediaMetadataRequest extends FormRequest
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
        $altMax = (int) config('media.metadata.alt_text_max_length', 300);
        $captionMax = (int) config('media.metadata.caption_max_length', 600);

        return [
            'translations' => ['sometimes', 'array'],
            'translations.*.locale' => ['required_with:translations', 'string', Rule::in(CatalogLocales::all())],
            'translations.*.alt_text' => ['sometimes', 'nullable', 'string', "max:{$altMax}"],
            'translations.*.caption' => ['sometimes', 'nullable', 'string', "max:{$captionMax}"],
            'focal_point' => ['sometimes', 'nullable', 'array'],
            'focal_point.x' => ['required_with:focal_point', 'numeric', 'min:0', 'max:1'],
            'focal_point.y' => ['required_with:focal_point', 'numeric', 'min:0', 'max:1'],
        ];
    }

    public function toData(): MediaMetadataData
    {
        $validated = $this->validated();

        /** @var array<string, array{alt_text?: string|null, caption?: string|null}>|null $translations */
        $translations = null;

        if (array_key_exists('translations', $validated)) {
            $translations = [];

            foreach ($validated['translations'] as $row) {
                $locale = (string) $row['locale'];
                $entry = [];

                if (array_key_exists('alt_text', $row)) {
                    $entry['alt_text'] = $row['alt_text'] === null ? null : (string) $row['alt_text'];
                }

                if (array_key_exists('caption', $row)) {
                    $entry['caption'] = $row['caption'] === null ? null : (string) $row['caption'];
                }

                $translations[$locale] = $entry;
            }
        }

        $focalProvided = array_key_exists('focal_point', $validated);
        $focal = $validated['focal_point'] ?? null;

        return new MediaMetadataData(
            translations: $translations,
            focalPointX: is_array($focal) && isset($focal['x']) ? (float) $focal['x'] : null,
            focalPointY: is_array($focal) && isset($focal['y']) ? (float) $focal['y'] : null,
            focalPointProvided: $focalProvided,
        );
    }
}
