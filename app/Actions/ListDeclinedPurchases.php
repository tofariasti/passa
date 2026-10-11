<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PurchaseDecision;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListDeclinedPurchases
{
    /** @return Builder<Purchase> */
    public function query(): Builder
    {
        return Purchase::query()
            ->where('decision', PurchaseDecision::Declined)
            ->oldest('occurred_at')
            ->orderBy('id');
    }
}
