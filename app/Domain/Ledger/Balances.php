<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Authorization\OpenHold;
use App\Enums\CardStatus;
use App\Enums\PurchaseDecision;
use App\Enums\PurchaseEventType;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\PurchaseEvent;
use Carbon\CarbonImmutable;

final class Balances
{
    public static function limitRemaining(Card $card, CarbonImmutable $moment): int
    {
        [$start, $end] = CardMonth::utcBounds($moment);

        $movement = (int) CardTransaction::query()
            ->where('card_id', $card->id)
            ->whereHas('purchase', function ($query) use ($start, $end): void {
                $query->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end);
            })
            ->sum('amount_cents');

        return $card->monthly_limit_cents + $movement;
    }

    public static function companyAvailable(Company $company): int
    {
        $balance = (int) $company->entries()->sum('amount_cents');

        $holds = Purchase::query()
            ->where('decision', PurchaseDecision::Approved)
            ->whereIn('card_id', Card::query()->where('company_id', $company->id)->select('id'))
            ->with('events')
            ->get()
            ->sum(function (Purchase $purchase): int {
                $cancelled = $purchase->events->contains(
                    fn (PurchaseEvent $event): bool => $event->type === PurchaseEventType::Cancellation,
                );
                $captures = $purchase->events->filter(
                    fn (PurchaseEvent $event): bool => $event->type === PurchaseEventType::Capture,
                );

                return OpenHold::cents(
                    authorizedCents: $purchase->amount_cents,
                    approved: true,
                    cancelled: $cancelled,
                    capturedCents: (int) $captures->sum('amount_cents'),
                    final: $captures->contains(fn (PurchaseEvent $event): bool => $event->is_final === true),
                );
            });

        return $balance - $holds;
    }

    public static function cardAvailable(Card $card, Company $company, CarbonImmutable $moment): int
    {
        if ($card->status === CardStatus::Blocked) {
            return 0;
        }

        return max(min(self::limitRemaining($card, $moment), self::companyAvailable($company)), 0);
    }

    public static function captureExceededCeiling(string $mcc, int $authorizedCents, int $capturedCents): bool
    {
        if (in_array($mcc, ['5812', '7011', '7512'], true)) {
            return $capturedCents * 100 > $authorizedCents * 120;
        }

        return $capturedCents > $authorizedCents;
    }

    public static function isProblem(Purchase $purchase): bool
    {
        $events = PurchaseEvent::query()->where('purchase_id', $purchase->id)->orderBy('id')->get();
        $cancellation = $events->first(
            fn (PurchaseEvent $event): bool => $event->type === PurchaseEventType::Cancellation,
        );
        $captures = $events->filter(
            fn (PurchaseEvent $event): bool => $event->type === PurchaseEventType::Capture,
        );
        $arrivedAfterCancellation = $cancellation instanceof PurchaseEvent && $captures->contains(
            fn (PurchaseEvent $event): bool => $event->id > $cancellation->id,
        );

        if ($arrivedAfterCancellation) {
            return true;
        }

        $final = $captures->contains(fn (PurchaseEvent $event): bool => $event->is_final === true);

        if (! $final) {
            return false;
        }

        return self::captureExceededCeiling($purchase->mcc, $purchase->amount_cents, (int) $captures->sum('amount_cents'));
    }
}
