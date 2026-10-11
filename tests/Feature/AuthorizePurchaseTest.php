<?php

declare(strict_types=1);

use App\Enums\CardTransactionType;
use App\Models\CardTransaction;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow(Date::parse('2026-10-08 15:00:00', 'America/Sao_Paulo'));
    $this->seed();
});

afterEach(function (): void {
    Date::setTestNow();
});

it('approves an amount equal to the card cap and reserves it once', function (): void {
    $body = authorizationBody([
        'id' => 'aut_ana_cap',
        'card_token' => 'tok_ana',
        'amount_cents' => 80_000,
    ]);

    postNetwork('/api/network/authorizations', $body)
        ->assertOk()
        ->assertExactJson(['decision' => 'approved']);

    Date::setTestNow(now()->addSeconds(10));

    postNetwork('/api/network/authorizations', $body)
        ->assertOk()
        ->assertExactJson(['decision' => 'approved']);

    expect(CardTransaction::query()->where('type', CardTransactionType::Hold)->count())->toBe(1)
        ->and(CardTransaction::query()->first()?->amount_cents)->toBe(-80_000);
});

it('declines for the first matching reason', function (string $token, int $amount, string $mcc, string $reason): void {
    $body = authorizationBody([
        'id' => 'aut_'.$reason,
        'card_token' => $token,
        'amount_cents' => $amount,
        'mcc' => $mcc,
    ]);

    postNetwork('/api/network/authorizations', $body)
        ->assertOk()
        ->assertExactJson(['decision' => 'declined', 'reason' => $reason]);

    expect(CardTransaction::query()->count())->toBe(0);
})->with([
    'missing card' => ['tok_nobody', 1_000, '5812', 'card_not_found'],
    'blocked card' => ['tok_carla', 90_000, '7995', 'card_blocked'],
    'blocked mcc before the cap' => ['tok_ana', 90_000, '7995', 'mcc_blocked'],
    'amount over the cap' => ['tok_ana', 80_001, '5812', 'amount_over_purchase_limit'],
]);

it('declines the next purchase that passes the monthly limit', function (): void {
    postNetwork('/api/network/authorizations', authorizationBody([
        'id' => 'aut_bruno_full',
        'card_token' => 'tok_bruno',
        'amount_cents' => 50_000,
    ]))->assertExactJson(['decision' => 'approved']);

    postNetwork('/api/network/authorizations', authorizationBody([
        'id' => 'aut_bruno_more',
        'card_token' => 'tok_bruno',
        'amount_cents' => 1,
    ]))->assertExactJson(['decision' => 'declined', 'reason' => 'monthly_limit_exceeded']);
});

it('checks the company balance after the card limit', function (): void {
    postNetwork('/api/network/authorizations', authorizationBody([
        'id' => 'aut_diego_balance',
        'card_token' => 'tok_diego',
        'amount_cents' => 1_000_000,
    ]))->assertExactJson(['decision' => 'approved']);

    postNetwork('/api/network/authorizations', authorizationBody([
        'id' => 'aut_diego_one_cent',
        'card_token' => 'tok_diego',
        'amount_cents' => 1,
    ]))->assertExactJson(['decision' => 'declined', 'reason' => 'insufficient_funds']);

    postNetwork('/api/network/authorizations', authorizationBody([
        'id' => 'aut_ana_no_funds',
        'card_token' => 'tok_ana',
        'amount_cents' => 1_000,
    ]))->assertExactJson(['decision' => 'declined', 'reason' => 'insufficient_funds']);

    postNetwork('/api/network/authorizations', authorizationBody([
        'id' => 'aut_diego_over_month',
        'card_token' => 'tok_diego',
        'amount_cents' => 4_000_001,
    ]))->assertExactJson(['decision' => 'declined', 'reason' => 'monthly_limit_exceeded']);
});

it('rejects a bad signature before reading the body', function (): void {
    postNetwork('/api/network/authorizations', '{', sign: false)->assertUnauthorized();

    $body = '{';
    $timestamp = now()->getTimestamp();
    postNetwork('/api/network/authorizations', $body, $timestamp, 'wrong-secret')->assertUnauthorized();
});

it('rejects a timestamp outside five minutes and accepts the boundary', function (): void {
    $body = authorizationBody(['id' => 'aut_stale']);

    $boundary = now()->getTimestamp() - 300;

    postNetwork('/api/network/authorizations', $body, $boundary - 1)->assertUnauthorized();

    $occurredAt = Date::createFromTimestamp($boundary, 'UTC')->format('Y-m-d\TH:i:s\Z');

    postNetwork('/api/network/authorizations', authorizationBody([
        'id' => 'aut_boundary',
        'occurred_at' => $occurredAt,
    ]), $boundary)->assertOk();
});

it('rejects a message that breaks the contract', function (string $body): void {
    postNetwork('/api/network/authorizations', $body)->assertUnprocessable();
})->with([
    'amount as text' => [authorizationBodyJson(['amount_cents' => '1000'])],
    'amount as float' => [authorizationBodyJson(['amount_cents' => 1000.5])],
    'zero amount' => [authorizationBodyJson(['amount_cents' => 0])],
    'lowercase country' => [authorizationBodyJson(['merchant' => ['name' => 'Padaria', 'city' => 'Porto Alegre', 'country' => 'br']])],
    'fact after the send' => [authorizationBodyJson(['occurred_at' => '2026-10-08T18:00:01Z'])],
    'empty body' => [''],
]);
