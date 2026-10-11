<?php

declare(strict_types=1);

namespace App\Actions;

use App\Domain\Ledger\Balances;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListProblemPurchases
{
    /** @return Builder<Purchase> */
    public function query(): Builder
    {
        $ids = Purchase::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (Purchase $purchase): bool => Balances::isProblem($purchase))
            ->map(fn (Purchase $purchase): int => $purchase->id)
            ->all();

        $query = Purchase::query()->oldest('occurred_at')->orderBy('id');

        if ($ids === []) {
            return $query->where('id', 0);
        }

        return $query->whereIn('id', $ids);
    }
}
