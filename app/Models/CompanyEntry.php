<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\UtcInstant;
use App\Enums\CompanyEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanyEntry extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'purchase_id',
        'purchase_event_id',
        'occurred_at',
        'type',
        'amount_cents',
        'reference',
    ];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return BelongsTo<PurchaseEvent, $this> */
    public function purchaseEvent(): BelongsTo
    {
        return $this->belongsTo(PurchaseEvent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => UtcInstant::class,
            'amount_cents' => 'integer',
            'type' => CompanyEntryType::class,
        ];
    }
}
