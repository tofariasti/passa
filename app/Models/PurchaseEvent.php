<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseEvent extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'purchase_id',
        'network_id',
        'authorization_network_id',
        'type',
        'occurred_at',
        'amount_cents',
        'currency',
        'sequence',
        'is_final',
    ];

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
            'sequence' => 'integer',
            'is_final' => 'boolean',
            'type' => PurchaseEventType::class,
        ];
    }
}
