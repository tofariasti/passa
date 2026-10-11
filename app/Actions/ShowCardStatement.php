<?php

declare(strict_types=1);

namespace App\Actions;

use App\Domain\Ledger\CardMonth;
use App\Models\Card;
use App\Models\CardTransaction;
use Carbon\CarbonImmutable;

final readonly class ShowCardStatement
{
    /**
     * The month is the authorization's, even when a capture line is dated later.
     *
     * @return array{month: string, limit_cents: int, limit_remaining_cents: int, transactions: list<array{occurred_at: string, type: string, amount_cents: int, reference: string, limit_remaining_after_cents: int}>}|null
     */
    public function handle(string $cardToken, CarbonImmutable $monthStart): ?array
    {
        $card = Card::query()->where('card_token', $cardToken)->first();

        if (! $card instanceof Card) {
            return null;
        }

        [$start, $end] = CardMonth::utcBounds($monthStart);
        $lines = CardTransaction::query()
            ->where('card_id', $card->id)
            ->whereHas('purchase', function ($query) use ($start, $end): void {
                $query->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end);
            })
            ->oldest('occurred_at')
            ->orderBy('reference')
            ->get();

        $remaining = $card->monthly_limit_cents;
        $transactions = [];

        foreach ($lines as $line) {
            $remaining += $line->amount_cents;
            $transactions[] = [
                'occurred_at' => $line->occurred_at->utc()->format('Y-m-d\TH:i:s\Z'),
                'type' => $line->type->value,
                'amount_cents' => $line->amount_cents,
                'reference' => $line->reference,
                'limit_remaining_after_cents' => $remaining,
            ];
        }

        return [
            'month' => $monthStart->timezone('America/Sao_Paulo')->format('Y-m'),
            'limit_cents' => $card->monthly_limit_cents,
            'limit_remaining_cents' => $remaining,
            'transactions' => $transactions,
        ];
    }
}
