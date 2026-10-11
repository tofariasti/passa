<?php

declare(strict_types=1);

use App\Domain\Authorization\DeclinePurchase;
use App\Domain\Authorization\OpenHold;
use App\Enums\DeclineReason;

it('declines for the first reason that applies', function (): void {
    $decline = new DeclinePurchase;

    expect($decline->reason(false, true, ['7995'], 100, 200, '7995', 0, 0))
        ->toBe(DeclineReason::CardNotFound)
        ->and($decline->reason(true, true, ['7995'], 100, 200, '7995', 0, 0))
        ->toBe(DeclineReason::CardBlocked)
        ->and($decline->reason(true, false, ['7995'], 100, 200, '7995', 0, 0))
        ->toBe(DeclineReason::MccBlocked)
        ->and($decline->reason(true, false, [], 100, 101, '5812', 1_000, 1_000))
        ->toBe(DeclineReason::AmountOverPurchaseLimit)
        ->and($decline->reason(true, false, [], null, 501, '5812', 500, 1_000))
        ->toBe(DeclineReason::MonthlyLimitExceeded)
        ->and($decline->reason(true, false, [], null, 501, '5812', 1_000, 500))
        ->toBe(DeclineReason::InsufficientFunds);
});

it('approves an amount equal to the cap, the remaining limit and the company balance', function (): void {
    $decline = new DeclinePurchase;

    expect($decline->reason(true, false, [], 800, 800, '5812', 800, 800))->toBeNull();
});

it('releases the hold when the purchase is final or cancelled', function (): void {
    expect(OpenHold::cents(80_000, true, false, 30_000, false))->toBe(50_000)
        ->and(OpenHold::cents(80_000, true, false, 86_000, true))->toBe(0)
        ->and(OpenHold::cents(80_000, true, true, 30_000, false))->toBe(0)
        ->and(OpenHold::cents(80_000, false, false, 0, false))->toBe(0);
});
