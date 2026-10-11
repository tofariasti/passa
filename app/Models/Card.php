<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CardStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Card extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'user_id',
        'card_token',
        'monthly_limit_cents',
        'purchase_limit_cents',
        'blocked_mccs',
        'status',
    ];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Purchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monthly_limit_cents' => 'integer',
            'purchase_limit_cents' => 'integer',
            'blocked_mccs' => 'array',
            'status' => CardStatus::class,
        ];
    }
}
