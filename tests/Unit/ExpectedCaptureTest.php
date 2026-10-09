<?php

declare(strict_types=1);

use App\Domain\Ledger\Balances;

it('keeps the twenty percent ceiling inclusive and the other mccs at the authorized amount', function (): void {
    expect(Balances::captureExceededCeiling('7011', 80_000, 96_000))->toBeFalse()
        ->and(Balances::captureExceededCeiling('7011', 80_000, 96_001))->toBeTrue()
        ->and(Balances::captureExceededCeiling('5812', 40_000, 48_000))->toBeFalse()
        ->and(Balances::captureExceededCeiling('5812', 40_000, 48_001))->toBeTrue()
        ->and(Balances::captureExceededCeiling('5411', 10_000, 10_000))->toBeFalse()
        ->and(Balances::captureExceededCeiling('5411', 10_000, 10_001))->toBeTrue();
});
