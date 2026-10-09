<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CardTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CardTransaction extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'card_id',
        'purchase_id',
        'occurred_at',
        'type',
        'amount_cents',
        'reference',
    ];

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'amount_cents' => 'integer',
            'type' => CardTransactionType::class,
        ];
    }
}
