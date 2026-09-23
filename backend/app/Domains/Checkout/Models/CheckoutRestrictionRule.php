<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Models;

use App\Domains\Checkout\Enums\CheckoutRestrictionOutcome;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuration-driven checkout restriction. Not a legal claim.
 *
 * @property int $id
 * @property int $product_id
 * @property CheckoutRestrictionOutcome $outcome
 * @property string $code
 * @property array<string, string> $message_translations
 * @property string|null $required_action
 * @property bool $is_active
 */
class CheckoutRestrictionRule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'outcome',
        'code',
        'message_translations',
        'required_action',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => CheckoutRestrictionOutcome::class,
            'message_translations' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function localizedMessage(string $locale): string
    {
        $value = $this->message_translations[$locale]
            ?? $this->message_translations['ka']
            ?? $this->message_translations['en']
            ?? $this->code;

        return is_string($value) ? $value : $this->code;
    }
}
