<?php

declare(strict_types=1);

use App\Enums\CardStatus;
use App\Enums\CompanyEntryType;
use App\Models\Card;
use App\Models\CompanyEntry;
use App\Models\User;

it('seeds the acme company, cards and opening deposit', function (): void {
    $this->seed();

    $ana = Card::query()->where('card_token', 'tok_ana')->first();

    expect($ana)->not->toBeNull()
        ->and($ana->monthly_limit_cents)->toBe(200_000)
        ->and($ana->purchase_limit_cents)->toBe(80_000)
        ->and($ana->blocked_mccs)->toBe(['7995'])
        ->and($ana->status)->toBe(CardStatus::Active)
        ->and($ana->user->email)->toBe('ana@acme.test');

    expect(Card::query()->where('card_token', 'tok_bruno')->first())
        ->purchase_limit_cents->toBeNull()
        ->blocked_mccs->toBe([])
        ->and(Card::query()->where('card_token', 'tok_carla')->first())
        ->status->toBe(CardStatus::Blocked)
        ->and(Card::query()->where('card_token', 'tok_diego')->first())
        ->monthly_limit_cents->toBe(5_000_000);

    expect((int) CompanyEntry::query()->where('type', CompanyEntryType::Deposit)->sum('amount_cents'))
        ->toBe(1_000_000);
});

it('lets only marina into the admin panel', function (): void {
    $this->seed();

    $panel = filament()->getPanel('admin');

    expect(User::query()->where('email', 'marina@acme.test')->first()?->canAccessPanel($panel))->toBeTrue()
        ->and(User::query()->where('email', 'ana@acme.test')->first()?->canAccessPanel($panel))->toBeFalse();
});
