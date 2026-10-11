<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Actions\ShowCardStatement;
use App\Domain\Ledger\CardMonth;
use App\Models\Card;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

final class CardStatementPage extends Page
{
    public Card $cardModel;

    public string $month = '';

    protected static ?string $slug = 'cards/{card}/statement';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(int $card): void
    {
        $found = Card::query()->find($card);

        abort_unless($found instanceof Card, 404);

        $this->cardModel = $found;
        $this->month = CarbonImmutable::now('America/Sao_Paulo')->format('Y-m');
    }

    public function getTitle(): string
    {
        return 'Extrato de '.$this->cardModel->card_token;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.admin.card-statement')
                ->viewData(fn (): array => [
                    'statement' => $this->statement(),
                ]),
        ]);
    }

    /**
     * @return array{month: string, limit_cents: int, limit_remaining_cents: int, transactions: list<array{occurred_at: string, type: string, amount_cents: int, reference: string, limit_remaining_after_cents: int}>}|null
     */
    public function statement(): ?array
    {
        $month = $this->month === '' ? null : $this->month;
        $start = CardMonth::parse($month, CarbonImmutable::now());

        if (! $start instanceof CarbonImmutable) {
            return null;
        }

        return resolve(ShowCardStatement::class)->handle($this->cardModel->card_token, $start);
    }
}
