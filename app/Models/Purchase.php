<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\UtcInstant;
use App\Enums\DeclineReason;
use App\Enums\PurchaseDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Purchase extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'card_id',
        'network_authorization_id',
        'amount_cents',
        'currency',
        'mcc',
        'merchant_name',
        'merchant_city',
        'merchant_country',
        'occurred_at',
        'decision',
        'reason',
    ];

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /** @return HasMany<PurchaseEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PurchaseEvent::class);
    }

    /** @return HasMany<CardTransaction, $this> */
    public function cardTransactions(): HasMany
    {
        return $this->hasMany(CardTransaction::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'occurred_at' => UtcInstant::class,
            'decision' => PurchaseDecision::class,
            'reason' => DeclineReason::class,
        ];
    }
}
