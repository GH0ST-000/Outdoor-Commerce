<?php

declare(strict_types=1);

namespace App\Domains\Checkout\Models;

use App\Domains\Checkout\Enums\CheckoutQuoteAdjustmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $checkout_quote_id
 * @property int|null $quote_line_id
 * @property CheckoutQuoteAdjustmentType $type
 * @property string $code
 * @property string $label
 * @property int $amount_minor
 * @property array<string, mixed>|null $metadata
 */
class CheckoutQuoteAdjustment extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'checkout_quote_id',
        'quote_line_id',
        'type',
        'code',
        'label',
        'amount_minor',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CheckoutQuoteAdjustmentType::class,
            'amount_minor' => 'integer',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<CheckoutQuote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuote::class, 'checkout_quote_id');
    }

    /**
     * @return BelongsTo<CheckoutQuoteLine, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(CheckoutQuoteLine::class, 'quote_line_id');
    }
}
