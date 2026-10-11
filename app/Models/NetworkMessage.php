<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NetworkMessage extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'purchase_id',
        'network_id',
        'fact_fingerprint',
        'http_status',
        'response_body',
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
            'http_status' => 'integer',
            'response_body' => 'array',
        ];
    }
}
