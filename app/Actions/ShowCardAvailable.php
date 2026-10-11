<?php

declare(strict_types=1);

namespace App\Actions;

use App\Domain\Ledger\Balances;
use App\Models\Card;
use App\Models\Company;
use Carbon\CarbonImmutable;

final readonly class ShowCardAvailable
{
    /** @return array{available_cents: int, limit_remaining_cents: int}|null */
    public function handle(string $cardToken, CarbonImmutable $moment): ?array
    {
        $card = Card::query()->where('card_token', $cardToken)->first();

        if (! $card instanceof Card) {
            return null;
        }

        $company = Company::query()->orderBy('id')->firstOrFail();

        return [
            'available_cents' => Balances::cardAvailable($card, $company, $moment),
            'limit_remaining_cents' => Balances::limitRemaining($card, $moment),
        ];
    }
}
