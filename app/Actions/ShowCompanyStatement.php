<?php

declare(strict_types=1);

namespace App\Actions;

use App\Domain\Ledger\Balances;
use App\Models\Company;

final readonly class ShowCompanyStatement
{
    /**
     * @return array{available_cents: int, balance_cents: int, lines: list<array{id: int, occurred_at: string, type: string, amount_cents: int, balance_after_cents: int, reference: string|null}>}
     */
    public function handle(): array
    {
        $company = Company::query()->orderBy('id')->firstOrFail();
        $balance = 0;
        $lines = [];

        foreach ($company->entries()->oldest('occurred_at')->orderBy('id')->get() as $entry) {
            $balance += $entry->amount_cents;
            $lines[] = [
                'id' => $entry->id,
                'occurred_at' => $entry->occurred_at->utc()->format('Y-m-d\TH:i:s\Z'),
                'type' => $entry->type->value,
                'amount_cents' => $entry->amount_cents,
                'balance_after_cents' => $balance,
                'reference' => $entry->reference,
            ];
        }

        return [
            'available_cents' => Balances::companyAvailable($company),
            'balance_cents' => $balance,
            'lines' => $lines,
        ];
    }
}
