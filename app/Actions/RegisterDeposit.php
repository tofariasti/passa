<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CompanyEntryType;
use App\Models\Company;
use App\Models\CompanyEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RegisterDeposit
{
    public function handle(int $amountCents): CompanyEntry
    {
        throw_if($amountCents < 1 || $amountCents > 100_000_000, InvalidArgumentException::class, 'Deposit amount must be between 1 and 100000000 cents.');

        return DB::transaction(function () use ($amountCents): CompanyEntry {
            // The network locks the company before it reads available. Take that lock first so a deposit and an authorization cannot both pass on a stale balance.
            $company = Company::query()->orderBy('id')->lockForUpdate()->firstOrFail();

            return $company->entries()->create([
                'occurred_at' => CarbonImmutable::now(),
                'type' => CompanyEntryType::Deposit,
                'amount_cents' => $amountCents,
            ]);
        });
    }
}
