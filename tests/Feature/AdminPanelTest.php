<?php

declare(strict_types=1);

use App\Actions\RegisterDeposit;
use App\Actions\ShowCardAvailable;
use App\Filament\Admin\Pages\CardsPage;
use App\Filament\Admin\Pages\CardStatementPage;
use App\Filament\Admin\Pages\CompanyStatementPage;
use App\Filament\Admin\Pages\DeclinedPurchasesPage;
use App\Filament\Admin\Pages\OrphanEventsPage;
use App\Filament\Admin\Pages\ProblemPurchasesPage;
use App\Filament\Admin\Pages\PurchaseHistoryPage;
use App\Models\Card;
use App\Models\Purchase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

beforeEach(function (): void {
    Date::setTestNow(Date::parse('2026-10-08 15:00:00', 'America/Sao_Paulo'));
    $this->seed();
    $this->actingAs(User::query()->where('email', 'marina@acme.test')->firstOrFail());
});

afterEach(function (): void {
    Date::setTestNow();
});

it('refuses a cardholder at the admin panel', function (): void {
    $this->actingAs(User::query()->where('email', 'ana@acme.test')->firstOrFail());

    $this->get('/admin')->assertForbidden();
    $this->get('/admin/cards')->assertForbidden();
});

it('shows the seed balances and an empty statement', function (): void {
    $this->get('/admin/cards')
        ->assertOk()
        ->assertSee('tok_ana')
        ->assertSee('tok_diego')
        ->assertSee('200000')
        ->assertSee('5000000')
        ->assertSee('Bloqueado');

    $moment = CarbonImmutable::now();

    expect(resolve(ShowCardAvailable::class)->handle('tok_carla', $moment))
        ->toBe(['available_cents' => 0, 'limit_remaining_cents' => 100_000])
        ->and(resolve(ShowCardAvailable::class)->handle('tok_diego', $moment)['available_cents'])->toBe(1_000_000);

    $ana = Card::query()->where('card_token', 'tok_ana')->firstOrFail();

    Livewire::test(CardStatementPage::class, ['card' => $ana->id])
        ->assertSee('Nenhuma transação neste mês.')
        ->assertSee('200000')
        ->set('month', '2026-13')
        ->assertSee('Mês inválido');
});

it('registers a deposit and raises the company available', function (): void {
    Livewire::test(CompanyStatementPage::class)
        ->assertSee('Disponível atual: 1000000 centavos')
        ->callAction('deposit', ['amount_cents' => 50_000])
        ->assertHasNoActionErrors()
        ->assertSee('Disponível atual: 1050000 centavos')
        ->assertSee('1050000');

    $moment = CarbonImmutable::now();

    expect(resolve(ShowCardAvailable::class)->handle('tok_diego', $moment)['available_cents'])->toBe(1_050_000)
        ->and(resolve(ShowCardAvailable::class)->handle('tok_ana', $moment)['available_cents'])->toBe(200_000);

    Livewire::test(CompanyStatementPage::class)
        ->callAction('deposit', ['amount_cents' => 0])
        ->assertHasActionErrors(['amount_cents']);

    expect(fn () => resolve(RegisterDeposit::class)->handle(100_000_001))
        ->toThrow(InvalidArgumentException::class);
});

it('lists a declined authorization and its history', function (): void {
    postNetwork('/api/network/authorizations', panelAuthorization([
        'id' => 'aut_blocked_mcc',
        'amount_cents' => 1_000,
        'mcc' => '7995',
    ]))->assertExactJson(['decision' => 'declined', 'reason' => 'mcc_blocked']);

    $purchase = Purchase::query()->where('network_authorization_id', 'aut_blocked_mcc')->firstOrFail();

    Livewire::test(DeclinedPurchasesPage::class)
        ->assertSee('aut_blocked_mcc')
        ->assertSee('mcc_blocked');

    Livewire::test(PurchaseHistoryPage::class, ['purchase' => $purchase->id])
        ->assertSee('Recusada')
        ->assertSee('mcc_blocked')
        ->assertSee('Autorização');
});

it('lists an orphan event until the authorization arrives', function (): void {
    panelInstant('2026-10-08T18:00:00Z');

    postNetwork('/api/network/events', panelCapture([
        'id' => 'evt_early',
        'authorization_id' => 'aut_later',
        'amount_cents' => 1_000,
        'final' => true,
    ]))->assertExactJson(['received' => true]);

    Livewire::test(OrphanEventsPage::class)->assertSee('evt_early');

    panelInstant('2026-10-08T18:01:00Z');

    postNetwork('/api/network/authorizations', panelAuthorization([
        'id' => 'aut_later',
        'amount_cents' => 1_000,
        'occurred_at' => '2026-10-08T18:01:00Z',
    ]))->assertExactJson(['decision' => 'approved']);

    Livewire::test(OrphanEventsPage::class)->assertDontSee('evt_early');
});

it('lists a capture that arrived after cancellation', function (): void {
    panelInstant('2026-10-08T18:00:00Z');

    postNetwork('/api/network/authorizations', panelAuthorization([
        'id' => 'aut_problem',
        'amount_cents' => 1_000,
    ]))->assertExactJson(['decision' => 'approved']);

    panelInstant('2026-10-08T18:01:00Z');

    postNetwork('/api/network/events', json_encode([
        'id' => 'evt_problem_cancel',
        'type' => 'cancellation',
        'authorization_id' => 'aut_problem',
        'occurred_at' => '2026-10-08T18:01:00Z',
    ], JSON_THROW_ON_ERROR))->assertExactJson(['received' => true]);

    panelInstant('2026-10-08T18:02:00Z');

    postNetwork('/api/network/events', panelCapture([
        'id' => 'evt_problem_after',
        'authorization_id' => 'aut_problem',
        'amount_cents' => 1_000,
        'occurred_at' => '2026-10-08T18:02:00Z',
        'final' => true,
    ]))->assertExactJson(['received' => true]);

    $purchase = Purchase::query()->where('network_authorization_id', 'aut_problem')->firstOrFail();

    Livewire::test(ProblemPurchasesPage::class)->assertSee('aut_problem');

    Livewire::test(PurchaseHistoryPage::class, ['purchase' => $purchase->id])
        ->assertSee('Autorização')
        ->assertSee('Cancelamento')
        ->assertSee('Captura final')
        ->assertSeeInOrder(['aut_problem', 'evt_problem_cancel', 'evt_problem_after']);
});

it('shows the published p2 figures on the panel', function (): void {
    panelInstant('2026-10-08T18:00:00Z');

    postNetwork('/api/network/authorizations', panelAuthorization([
        'id' => 'aut_panel_p2',
        'amount_cents' => 80_000,
        'mcc' => '7011',
    ]))->assertExactJson(['decision' => 'approved']);

    foreach ([1 => false, 2 => false, 3 => true] as $sequence => $final) {
        $at = sprintf('2026-10-08T18:0%d:00Z', $sequence);
        panelInstant($at);
        postNetwork('/api/network/events', panelCapture([
            'id' => 'evt_panel_p2_'.$sequence,
            'authorization_id' => 'aut_panel_p2',
            'amount_cents' => $sequence === 3 ? 26_000 : 30_000,
            'sequence' => $sequence,
            'final' => $final,
            'occurred_at' => $at,
        ]))->assertExactJson(['received' => true]);
    }

    Livewire::test(CardsPage::class)
        ->assertSee('114000')
        ->assertSee('914000')
        ->assertSee('5000000');

    Livewire::test(CompanyStatementPage::class)
        ->assertSee('Disponível atual: 914000 centavos')
        ->assertSee('914000');

    $ana = Card::query()->where('card_token', 'tok_ana')->firstOrFail();

    Livewire::test(CardStatementPage::class, ['card' => $ana->id])
        ->assertSee('114000')
        ->assertSee('evt_panel_p2_3')
        ->assertSee('Ajuste');

    Livewire::test(ProblemPurchasesPage::class)->assertDontSee('aut_panel_p2');
});

function panelInstant(string $utc): void
{
    Date::setTestNow(Date::parse($utc, 'UTC'));
}

function panelAuthorization(array $overrides = []): string
{
    $payload = array_replace([
        'id' => 'aut_panel',
        'card_token' => 'tok_ana',
        'amount_cents' => 1_000,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => ['name' => 'Padaria', 'city' => 'Porto Alegre', 'country' => 'BR'],
        'occurred_at' => '2026-10-08T18:00:00Z',
    ], $overrides);

    return json_encode($payload, JSON_THROW_ON_ERROR);
}

function panelCapture(array $overrides = []): string
{
    $payload = array_replace([
        'id' => 'evt_panel',
        'type' => 'capture',
        'occurred_at' => '2026-10-08T18:00:00Z',
        'authorization_id' => 'aut_panel',
        'amount_cents' => 1_000,
        'currency' => 'BRL',
        'sequence' => 1,
        'final' => true,
    ], $overrides);

    return json_encode($payload, JSON_THROW_ON_ERROR);
}
