<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use App\Enums\DeclineReason;

final class DeclinePurchase
{
    /**
     * @param  list<string>  $blockedMccs
     */
    public function reason(
        bool $cardExists,
        bool $cardBlocked,
        array $blockedMccs,
        ?int $purchaseLimitCents,
        int $amountCents,
        string $mcc,
        int $limitRemainingCents,
        int $companyAvailableCents,
    ): ?DeclineReason {
        if (! $cardExists) {
            return DeclineReason::CardNotFound;
        }

        if ($cardBlocked) {
            return DeclineReason::CardBlocked;
        }

        if (in_array($mcc, $blockedMccs, true)) {
            return DeclineReason::MccBlocked;
        }

        if ($purchaseLimitCents !== null && $amountCents > $purchaseLimitCents) {
            return DeclineReason::AmountOverPurchaseLimit;
        }

        if ($amountCents > $limitRemainingCents) {
            return DeclineReason::MonthlyLimitExceeded;
        }

        if ($amountCents > $companyAvailableCents) {
            return DeclineReason::InsufficientFunds;
        }

        return null;
    }
}
