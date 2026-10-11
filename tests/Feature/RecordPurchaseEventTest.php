<?php

declare(strict_types=1);

use App\Domain\Ledger\Balances;
use App\Enums\CardTransactionType;
use App\Enums\CompanyEntryType;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Company;
use App\Models\CompanyEntry;
use App\Models\NetworkMessage;
use App\Models\Purchase;
use App\Models\PurchaseEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Date::setTestNow(Date::parse('2026-10-08 15:00:00', 'America/Sao_Paulo'));
    $this->seed();
});

afterEach(function (): void {
    Date::setTestNow();
});

it('follows the published p2 captures', function (): void {
    $approvedAt = freezeUtc('2026-10-08T18:00:00Z');

    postNetwork('/api/network/authorizations', authorizationBody([
        'id' => 'aut_p2',
        'card_token' => 'tok_ana',
        'amount_cents' => 80_000,
        'mcc' => '7011',
        'occurred_at' => $approvedAt,
    ]))->assertExactJson(['decision' => 'approved']);

    expect(figures('tok_ana'))->toBe(['limit' => 120_000, 'available' => 120_000])
        ->and(figures('tok_diego'))->toBe(['limit' => 5_000_000, 'available' => 920_000]);

    foreach ([1, 2] as $sequence) {
        postCapture('aut_p2', 'evt_p2_'.$sequence, $sequence, 30_000, false, '2026-10-08T18:0'.$sequence.':00Z');

        expect(figures('tok_ana'))->toBe(['limit' => 120_000, 'available' => 120_000])
            ->and(figures('tok_diego')['available'])->toBe(920_000)
            ->and(CardTransaction::query()->where('type', CardTransactionType::Adjustment)->count())->toBe(0);
    }

    postCapture('aut_p2', 'evt_p2_3', 3, 26_000, true, '2026-10-08T18:03:00Z');

    $purchase = Purchase::query()->where('network_authorization_id', 'aut_p2')->firstOrFail();

    expect(figures('tok_ana'))->toBe(['limit' => 114_000, 'available' => 114_000])
        ->and(figures('tok_diego'))->toBe(['limit' => 5_000_000, 'available' => 914_000])
        ->and(Balances::isProblem($purchase))->toBeFalse()
        ->and((int) CardTransaction::query()->where('reference', 'evt_p2_3')->value('amount_cents'))->toBe(-6_000)
        ->and((int) CompanyEntry::query()->where('type', CompanyEntryType::Capture)->sum('amount_cents'))->toBe(-86_000);
});

it('follows the published p3 captures', function (): void {
    postAuthorization('aut_p3_meal', 'tok_bruno', 40_000, '5812')
        ->assertExactJson(['decision' => 'approved']);
    postCapture('aut_p3_meal', 'evt_p3_meal', 1, 48_000, true, '2026-10-08T18:01:00Z');

    $meal = Purchase::query()->where('network_authorization_id', 'aut_p3_meal')->firstOrFail();

    expect(figures('tok_bruno'))->toBe(['limit' => 2_000, 'available' => 2_000])
        ->and(figures('tok_diego')['available'])->toBe(952_000)
        ->and(Balances::isProblem($meal))->toBeFalse();

    postAuthorization('aut_p3_over', 'tok_bruno', 5_000, '5812')
        ->assertExactJson(['decision' => 'declined', 'reason' => 'monthly_limit_exceeded']);

    expect(figures('tok_bruno'))->toBe(['limit' => 2_000, 'available' => 2_000])
        ->and(figures('tok_diego')['available'])->toBe(952_000);

    postAuthorization('aut_p3_rest', 'tok_bruno', 2_000, '5812')
        ->assertExactJson(['decision' => 'approved']);

    expect(figures('tok_bruno'))->toBe(['limit' => 0, 'available' => 0])
        ->and(figures('tok_diego')['available'])->toBe(950_000)
        ->and(Balances::isProblem($meal))->toBeFalse();
});

it('ends at the same card total when the final capture arrives first', function (): void {
    postAuthorization('aut_order', 'tok_ana', 80_000, '7011');
    postCapture('aut_order', 'evt_order_final', 2, 26_000, true, '2026-10-08T18:02:00Z');
    postCapture('aut_order', 'evt_order_first', 1, 30_000, false, '2026-10-08T18:01:00Z');

    $purchase = Purchase::query()->where('network_authorization_id', 'aut_order')->firstOrFail();

    expect(figures('tok_ana'))->toBe(['limit' => 144_000, 'available' => 144_000])
        ->and(figures('tok_diego')['available'])->toBe(944_000)
        ->and((int) CardTransaction::query()->sum('amount_cents'))->toBe(-56_000)
        ->and(Balances::isProblem($purchase))->toBeFalse();
});

it('returns the stored event and does not apply a reissue', function (): void {
    postAuthorization('aut_replay', 'tok_ana', 10_000, '5411');
    $body = eventBody([
        'id' => 'evt_replay',
        'authorization_id' => 'aut_replay',
        'amount_cents' => 4_000,
        'sequence' => 1,
        'final' => false,
        'occurred_at' => freezeUtc('2026-10-08T18:01:00Z'),
    ]);

    postNetwork('/api/network/events', $body)->assertExactJson(['received' => true]);

    Date::setTestNow(now()->addSeconds(10));

    postNetwork('/api/network/events', $body)->assertExactJson(['received' => true]);
    postNetwork('/api/network/events', eventBody([
        'id' => 'evt_replay_again',
        'authorization_id' => 'aut_replay',
        'amount_cents' => 4_000,
        'sequence' => 1,
        'final' => false,
        'occurred_at' => '2026-10-08T18:01:00Z',
    ]))->assertExactJson(['received' => true]);

    expect(PurchaseEvent::query()->count())->toBe(1)
        ->and(CompanyEntry::query()->where('type', CompanyEntryType::Capture)->count())->toBe(1)
        ->and(NetworkMessage::query()->whereIn('network_id', ['evt_replay', 'evt_replay_again'])->count())->toBe(2);
});

it('applies an event that arrived before the authorization only after the decision', function (): void {
    postCapture('aut_early', 'evt_early', 1, 150_000, true, '2026-10-08T17:59:00Z');

    postAuthorization('aut_early', 'tok_ana', 60_000, '5411')
        ->assertExactJson(['decision' => 'approved']);

    $purchase = Purchase::query()->where('network_authorization_id', 'aut_early')->firstOrFail();

    expect(figures('tok_ana')['limit'])->toBe(50_000)
        ->and((int) CompanyEntry::query()->where('type', CompanyEntryType::Capture)->sum('amount_cents'))->toBe(-150_000)
        ->and(Balances::isProblem($purchase))->toBeTrue()
        ->and($purchase->events()->count())->toBe(1);
});

it('accepts a capture after cancellation and marks the purchase', function (): void {
    postAuthorization('aut_late', 'tok_ana', 10_000, '5411');
    postNetwork('/api/network/events', eventBody([
        'id' => 'evt_late_cancel',
        'type' => 'cancellation',
        'authorization_id' => 'aut_late',
        'amount_cents' => 10_000,
        'occurred_at' => freezeUtc('2026-10-08T18:01:00Z'),
    ]))->assertExactJson(['received' => true]);

    postCapture('aut_late', 'evt_late_capture', 1, 4_000, false, '2026-10-08T18:02:00Z');

    $purchase = Purchase::query()->where('network_authorization_id', 'aut_late')->firstOrFail();

    expect(Balances::isProblem($purchase))->toBeTrue()
        ->and(figures('tok_ana'))->toBe(['limit' => 196_000, 'available' => 196_000])
        ->and((int) CardTransaction::query()->sum('amount_cents'))->toBe(-4_000)
        ->and((int) CompanyEntry::query()->where('type', CompanyEntryType::Capture)->sum('amount_cents'))->toBe(-4_000);
});

it('keeps an open purchase off the problem list', function (): void {
    postAuthorization('aut_open', 'tok_ana', 10_000, '5411');
    postCapture('aut_open', 'evt_open', 1, 10_001, false, '2026-10-08T18:01:00Z');

    $purchase = Purchase::query()->where('network_authorization_id', 'aut_open')->firstOrFail();

    expect(Balances::isProblem($purchase))->toBeFalse()
        ->and((int) CardTransaction::query()->sum('amount_cents'))->toBe(-10_001);
});

it('rejects a bad event signature before reading the body', function (): void {
    postNetwork('/api/network/events', '{', sign: false)->assertUnauthorized();
});

it('rejects an event that breaks the contract', function (string $body): void {
    postNetwork('/api/network/events', $body)->assertUnprocessable();
})->with([
    'amount as text' => [eventBody(['amount_cents' => '1000'])],
    'sequence zero' => [eventBody(['sequence' => 0])],
    'final as text' => [eventBody(['final' => 'yes'])],
    'unknown type' => [eventBody(['type' => 'refund'])],
    'fact after the send' => [eventBody(['occurred_at' => '2026-10-08T18:00:01Z'])],
]);

function freezeUtc(string $instant): string
{
    Date::setTestNow(Date::parse($instant, 'UTC'));

    return $instant;
}

function postAuthorization(string $id, string $token, int $amount, string $mcc): TestResponse
{
    return postNetwork('/api/network/authorizations', authorizationBody([
        'id' => $id,
        'card_token' => $token,
        'amount_cents' => $amount,
        'mcc' => $mcc,
        'occurred_at' => freezeUtc('2026-10-08T18:00:00Z'),
    ]));
}

function postCapture(string $authorizationId, string $id, int $sequence, int $amount, bool $final, string $occurredAt): void
{
    postNetwork('/api/network/events', eventBody([
        'id' => $id,
        'authorization_id' => $authorizationId,
        'amount_cents' => $amount,
        'sequence' => $sequence,
        'final' => $final,
        'occurred_at' => freezeUtc($occurredAt),
    ]))->assertExactJson(['received' => true]);
}

/** @return array{limit: int, available: int} */
function figures(string $token): array
{
    $card = Card::query()->where('card_token', $token)->firstOrFail();
    $company = Company::query()->firstOrFail();
    $moment = CarbonImmutable::instance(now());

    return [
        'limit' => Balances::limitRemaining($card, $moment),
        'available' => Balances::cardAvailable($card, $company, $moment),
    ];
}

function eventBody(array $overrides = []): string
{
    $payload = array_replace([
        'id' => 'evt_default',
        'type' => 'capture',
        'occurred_at' => '2026-10-08T18:00:00Z',
        'authorization_id' => 'aut_default',
        'amount_cents' => 1_000,
        'currency' => 'BRL',
        'sequence' => 1,
        'final' => false,
        'note' => 'ignored',
    ], $overrides);

    return json_encode($payload, JSON_THROW_ON_ERROR);
}
