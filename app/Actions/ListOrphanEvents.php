<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\PurchaseEvent;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListOrphanEvents
{
    /** @return Builder<PurchaseEvent> */
    public function query(): Builder
    {
        return PurchaseEvent::query()
            ->whereNull('purchase_id')
            ->oldest('occurred_at')
            ->orderBy('id');
    }
}
