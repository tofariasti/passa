<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CardStatus;
use App\Enums\CompanyEntryType;
use App\Models\Card;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

final class AcmeSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrCreate(['name' => 'Acme']);

        if ($company->wasRecentlyCreated) {
            $company->entries()->create([
                'occurred_at' => CarbonImmutable::parse('2026-01-01 00:00:00', 'America/Sao_Paulo'),
                'type' => CompanyEntryType::Deposit,
                'amount_cents' => 1_000_000,
            ]);
        }

        foreach ($this->cards() as $card) {
            $user = User::query()->firstOrCreate(
                ['email' => $card['email']],
                ['name' => $card['name'], 'password' => 'password'],
            );

            Card::query()->firstOrCreate(
                ['card_token' => $card['card_token']],
                [
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'monthly_limit_cents' => $card['monthly_limit_cents'],
                    'purchase_limit_cents' => $card['purchase_limit_cents'],
                    'blocked_mccs' => $card['blocked_mccs'],
                    'status' => $card['status'],
                ],
            );
        }
    }

    /**
     * @return list<array{email: string, name: string, card_token: string, monthly_limit_cents: int, purchase_limit_cents: int|null, blocked_mccs: list<string>, status: CardStatus}>
     */
    private function cards(): array
    {
        return [
            [
                'email' => 'ana@acme.test',
                'name' => 'Ana',
                'card_token' => 'tok_ana',
                'monthly_limit_cents' => 200_000,
                'purchase_limit_cents' => 80_000,
                'blocked_mccs' => ['7995'],
                'status' => CardStatus::Active,
            ],
            [
                'email' => 'bruno@acme.test',
                'name' => 'Bruno',
                'card_token' => 'tok_bruno',
                'monthly_limit_cents' => 50_000,
                'purchase_limit_cents' => null,
                'blocked_mccs' => [],
                'status' => CardStatus::Active,
            ],
            [
                'email' => 'carla@acme.test',
                'name' => 'Carla',
                'card_token' => 'tok_carla',
                'monthly_limit_cents' => 100_000,
                'purchase_limit_cents' => null,
                'blocked_mccs' => [],
                'status' => CardStatus::Blocked,
            ],
            [
                'email' => 'diego@acme.test',
                'name' => 'Diego',
                'card_token' => 'tok_diego',
                'monthly_limit_cents' => 5_000_000,
                'purchase_limit_cents' => null,
                'blocked_mccs' => [],
                'status' => CardStatus::Active,
            ],
        ];
    }
}
