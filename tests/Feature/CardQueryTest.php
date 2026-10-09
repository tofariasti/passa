<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Date::setTestNow(Date::parse('2026-10-08 15:00:00', 'America/Sao_Paulo'));
    $this->seed();
});

afterEach(function (): void {
    Date::setTestNow();
});

it('reports the current month and keeps the statement invariant', function (): void {
    postNetwork('/api/network/authorizations', queryAuthorization([
        'id' => 'aut_query',
        'card_token' => 'tok_ana',
        'amount_cents' => 80_000,
        'mcc' => '7011',
        'occurred_at' => '2026-10-08T18:00:00Z',
    ]))->assertExactJson(['decision' => 'approved']);

    foreach ([1 => 30_000, 2 => 30_000] as $sequence => $amount) {
        Date::setTestNow(Date::parse('2026-10-08 15:0'.$sequence.':00', 'America/Sao_Paulo'));
        postNetwork('/api/network/events', queryCapture([
            'id' => 'evt_query_'.$sequence,
            'authorization_id' => 'aut_query',
            'amount_cents' => $amount,
            'sequence' => $sequence,
            'final' => false,
            'occurred_at' => '2026-10-08T18:0'.$sequence.':00Z',
        ]))->assertExactJson(['received' => true]);
    }

    Date::setTestNow(Date::parse('2026-10-08 15:03:00', 'America/Sao_Paulo'));
    postNetwork('/api/network/events', queryCapture([
        'id' => 'evt_query_3',
        'authorization_id' => 'aut_query',
        'amount_cents' => 26_000,
        'sequence' => 3,
        'final' => true,
        'occurred_at' => '2026-10-08T18:03:00Z',
    ]))->assertExactJson(['received' => true]);

    $statement = getNetwork('/api/network/cards/tok_ana/statement')
        ->assertOk()
        ->assertExactJson([
            'month' => '2026-10',
            'limit_cents' => 200_000,
            'limit_remaining_cents' => 114_000,
            'transactions' => [
                [
                    'occurred_at' => '2026-10-08T18:00:00Z',
                    'type' => 'hold',
                    'amount_cents' => -80_000,
                    'reference' => 'aut_query',
                    'limit_remaining_after_cents' => 120_000,
                ],
                [
                    'occurred_at' => '2026-10-08T18:03:00Z',
                    'type' => 'adjustment',
                    'amount_cents' => -6_000,
                    'reference' => 'evt_query_3',
                    'limit_remaining_after_cents' => 114_000,
                ],
            ],
        ]);

    getNetwork('/api/network/cards/tok_ana/available')
        ->assertOk()
        ->assertExactJson([
            'available_cents' => 114_000,
            'limit_remaining_cents' => 114_000,
        ]);

    getNetwork('/api/network/cards/tok_diego/available')
        ->assertOk()
        ->assertExactJson([
            'available_cents' => 914_000,
            'limit_remaining_cents' => 5_000_000,
        ]);

    assertStatementInvariant($statement);
});

it('keeps an earlier statement line when a later capture joins that month', function (): void {
    postNetwork('/api/network/authorizations', queryAuthorization([
        'id' => 'aut_sept',
        'amount_cents' => 10_000,
        'mcc' => '5411',
        'occurred_at' => '2026-09-15T18:00:00Z',
    ]))->assertExactJson(['decision' => 'approved']);

    Date::setTestNow(Date::parse('2026-09-15 15:01:00', 'America/Sao_Paulo'));
    postNetwork('/api/network/events', queryCapture([
        'id' => 'evt_sept',
        'authorization_id' => 'aut_sept',
        'amount_cents' => 10_000,
        'sequence' => 1,
        'final' => true,
        'occurred_at' => '2026-09-15T18:01:00Z',
    ]))->assertExactJson(['received' => true]);

    Date::setTestNow(Date::parse('2026-10-08 15:00:00', 'America/Sao_Paulo'));

    $before = getNetwork('/api/network/cards/tok_ana/statement?month=2026-09')
        ->assertOk()
        ->json('transactions');

    postNetwork('/api/network/events', queryCapture([
        'id' => 'evt_oct',
        'authorization_id' => 'aut_sept',
        'amount_cents' => 1_000,
        'sequence' => 2,
        'final' => false,
        'occurred_at' => '2026-10-08T18:00:00Z',
    ]))->assertExactJson(['received' => true]);

    $after = getNetwork('/api/network/cards/tok_ana/statement?month=2026-09')->assertOk();
    $lines = $after->json('transactions');

    expect($lines[0])->toBe($before[0])
        ->and($lines[1]['reference'])->toBe('evt_oct')
        ->and($lines[1]['occurred_at'])->toBe('2026-10-08T18:00:00Z');

    getNetwork('/api/network/cards/tok_ana/statement?month=2026-10')
        ->assertOk()
        ->assertExactJson([
            'month' => '2026-10',
            'limit_cents' => 200_000,
            'limit_remaining_cents' => 200_000,
            'transactions' => [],
        ]);

    assertStatementInvariant($after);
});

it('orders lines that share a timestamp by reference', function (): void {
    postNetwork('/api/network/authorizations', queryAuthorization([
        'id' => 'aut_tie',
        'amount_cents' => 1_000,
        'mcc' => '5411',
        'occurred_at' => '2026-10-08T18:00:00Z',
    ]));

    postNetwork('/api/network/events', queryCapture([
        'id' => 'evt_tie',
        'authorization_id' => 'aut_tie',
        'amount_cents' => 2_000,
        'sequence' => 1,
        'final' => false,
        'occurred_at' => '2026-10-08T18:00:00Z',
    ]));

    $statement = getNetwork('/api/network/cards/tok_ana/statement?month=2026-10')->assertOk();
    $references = array_column($statement->json('transactions'), 'reference');

    expect($references)->toBe(['aut_tie', 'evt_tie']);
    assertStatementInvariant($statement);
});

it('attributes a purchase to the Sao Paulo month of the authorization', function (): void {
    postNetwork('/api/network/authorizations', queryAuthorization([
        'id' => 'aut_boundary',
        'amount_cents' => 1_000,
        'mcc' => '5411',
        'occurred_at' => '2026-10-01T02:30:00Z',
    ]))->assertExactJson(['decision' => 'approved']);

    getNetwork('/api/network/cards/tok_ana/statement?month=2026-09')
        ->assertOk()
        ->assertJsonPath('transactions.0.reference', 'aut_boundary');

    getNetwork('/api/network/cards/tok_ana/statement?month=2026-10')
        ->assertOk()
        ->assertExactJson([
            'month' => '2026-10',
            'limit_cents' => 200_000,
            'limit_remaining_cents' => 200_000,
            'transactions' => [],
        ]);
});

it('answers an empty month and a blocked card', function (): void {
    getNetwork('/api/network/cards/tok_ana/statement?month=2026-08')
        ->assertOk()
        ->assertExactJson([
            'month' => '2026-08',
            'limit_cents' => 200_000,
            'limit_remaining_cents' => 200_000,
            'transactions' => [],
        ]);

    getNetwork('/api/network/cards/tok_carla/available')
        ->assertExactJson([
            'available_cents' => 0,
            'limit_remaining_cents' => 100_000,
        ]);
});

it('rejects a bad signature before looking up the card', function (): void {
    getNetwork('/api/network/cards/tok_nobody/available', sign: false)->assertUnauthorized();
    getNetwork('/api/network/cards/tok_ana/statement?month=2026-13', sign: false)->assertUnauthorized();
});

it('rejects an unknown card and a bad month', function (string $uri, int $status): void {
    getNetwork($uri)->assertStatus($status);
})->with([
    'missing card available' => ['/api/network/cards/tok_nobody/available', 404],
    'missing card statement' => ['/api/network/cards/tok_nobody/statement', 404],
    'month 13' => ['/api/network/cards/tok_ana/statement?month=2026-13', 422],
    'month without the leading zero' => ['/api/network/cards/tok_ana/statement?month=2026-1', 422],
    'full date' => ['/api/network/cards/tok_ana/statement?month=2026-10-01', 422],
]);

function queryAuthorization(array $overrides): string
{
    return json_encode(array_replace([
        'id' => 'aut_query_default',
        'card_token' => 'tok_ana',
        'amount_cents' => 1_000,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => ['name' => 'Padaria', 'city' => 'Porto Alegre', 'country' => 'BR'],
        'occurred_at' => '2026-10-08T18:00:00Z',
    ], $overrides), JSON_THROW_ON_ERROR);
}

function queryCapture(array $overrides): string
{
    return json_encode(array_replace([
        'id' => 'evt_query_default',
        'type' => 'capture',
        'occurred_at' => '2026-10-08T18:00:00Z',
        'authorization_id' => 'aut_query_default',
        'amount_cents' => 1_000,
        'currency' => 'BRL',
        'sequence' => 1,
        'final' => false,
    ], $overrides), JSON_THROW_ON_ERROR);
}

function assertStatementInvariant(TestResponse $response): void
{
    /** @var array{limit_cents: int, limit_remaining_cents: int, transactions: list<array{amount_cents: int, limit_remaining_after_cents: int}>} $body */
    $body = $response->json();
    $remaining = $body['limit_cents'];

    foreach ($body['transactions'] as $line) {
        $remaining += $line['amount_cents'];
        expect($line['limit_remaining_after_cents'])->toBe($remaining);
    }

    expect($body['limit_remaining_cents'])->toBe($remaining);
}
