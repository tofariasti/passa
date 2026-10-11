<?php

declare(strict_types=1);

use App\Livewire\Login;
use App\Livewire\MyCard;
use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

beforeEach(function (): void {
    Date::setTestNow(Date::parse('2026-10-08 15:00:00', 'America/Sao_Paulo'));
    $this->seed();
});

afterEach(function (): void {
    Date::setTestNow();
});

it('sends a guest from my card to the login form', function (): void {
    $this->get('/my-card')->assertRedirect('/login');

    $this->get('/login')
        ->assertOk()
        ->assertSee('Entrar')
        ->assertDontSee('Cadastro')
        ->assertDontSee('Esqueci');

    $this->get('/admin')->assertRedirect('/admin/login');
});

it('logs the cardholder in and returns an authenticated visit to the form', function (): void {
    Livewire::test(Login::class)
        ->set('email', 'ana@acme.test')
        ->set('password', 'password')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect('/my-card');

    $this->get('/login')->assertRedirect('/my-card');
});

it('rejects a wrong password', function (): void {
    Livewire::test(Login::class)
        ->set('email', 'ana@acme.test')
        ->set('password', 'errada')
        ->call('authenticate')
        ->assertHasErrors(['email'])
        ->assertSee('E-mail ou senha incorretos.');

    $this->assertGuest();
});

it('refuses a user without a card', function (): void {
    Livewire::test(Login::class)
        ->set('email', 'marina@acme.test')
        ->set('password', 'password')
        ->call('authenticate')
        ->assertRedirect('/my-card');

    $this->get('/my-card')->assertForbidden();
});

it('shows only the authenticated card and rejects another card url', function (): void {
    $ana = User::query()->where('email', 'ana@acme.test')->firstOrFail();
    $bruno = Card::query()->where('card_token', 'tok_bruno')->firstOrFail();

    $this->actingAs($ana);

    $this->get('/my-card?card_token=tok_bruno')
        ->assertOk()
        ->assertSee('tok_ana')
        ->assertSee('200000')
        ->assertSee('Nenhuma transação neste mês.')
        ->assertSee('Nenhuma compra.')
        ->assertSee('wire:poll.4s')
        ->assertDontSee('tok_bruno')
        ->assertDontSee('tok_diego');

    $this->get('/my-card/'.$bruno->id)->assertNotFound();
});

it('refreshes the same screen after an authorization and a capture', function (): void {
    $this->actingAs(User::query()->where('email', 'ana@acme.test')->firstOrFail());

    $screen = Livewire::test(MyCard::class)
        ->assertSee('200000')
        ->assertDontSee('aut_holder');

    postNetwork('/api/network/authorizations', holderAuthorization([
        'id' => 'aut_holder',
        'amount_cents' => 1_000,
    ]))->assertExactJson(['decision' => 'approved']);

    $screen->refresh()
        ->assertSee('199000')
        ->assertSee('aut_holder')
        ->assertSee('Aprovada')
        ->assertSee('Autorização')
        ->assertSee('Reserva');

    postNetwork('/api/network/events', holderCapture([
        'id' => 'evt_holder',
        'authorization_id' => 'aut_holder',
    ]))->assertExactJson(['received' => true]);

    $screen->refresh()
        ->assertSee('evt_holder')
        ->assertSee('Captura final');
});

function holderAuthorization(array $overrides = []): string
{
    $payload = array_replace([
        'id' => 'aut_holder',
        'card_token' => 'tok_ana',
        'amount_cents' => 1_000,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => ['name' => 'Padaria', 'city' => 'Porto Alegre', 'country' => 'BR'],
        'occurred_at' => '2026-10-08T18:00:00Z',
    ], $overrides);

    return json_encode($payload, JSON_THROW_ON_ERROR);
}

function holderCapture(array $overrides = []): string
{
    $payload = array_replace([
        'id' => 'evt_holder',
        'type' => 'capture',
        'occurred_at' => '2026-10-08T18:00:00Z',
        'authorization_id' => 'aut_holder',
        'amount_cents' => 1_000,
        'currency' => 'BRL',
        'sequence' => 1,
        'final' => true,
    ], $overrides);

    return json_encode($payload, JSON_THROW_ON_ERROR);
}
