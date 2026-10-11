<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Actions\ShowCardAvailable;
use App\Enums\CardStatus;
use App\Models\Card;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

final class CardsPage extends Page implements HasTable
{
    use RendersAdminTable;

    protected static ?string $slug = 'cards';

    protected static ?string $title = 'Cartões';

    protected static ?string $navigationLabel = 'Cartões';

    protected static ?int $navigationSort = 1;

    protected static string|UnitEnum|null $navigationGroup = 'Acme';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    public function table(Table $table): Table
    {
        return $table
            ->query(Card::query()->with('user'))
            ->paginated(false)
            ->emptyStateHeading('Nenhum cartão')
            ->columns([
                TextColumn::make('user.name')->label('Portador'),
                TextColumn::make('card_token')->label('Token'),
                TextColumn::make('status')
                    ->label('Situação')
                    ->formatStateUsing(fn (CardStatus $state): string => match ($state) {
                        CardStatus::Active => 'Ativo',
                        CardStatus::Blocked => 'Bloqueado',
                    }),
                TextColumn::make('available_cents')
                    ->label('Disponível (centavos)')
                    ->state(fn (Card $record): string => (string) $this->figures($record)['available_cents']),
                TextColumn::make('limit_remaining_cents')
                    ->label('Limite restante (centavos)')
                    ->state(fn (Card $record): string => (string) $this->figures($record)['limit_remaining_cents']),
            ])
            ->recordActions([
                Action::make('statement')
                    ->label('Extrato')
                    ->url(fn (Card $record): string => CardStatementPage::getUrl(['card' => $record->id])),
            ]);
    }

    /** @return array{available_cents: int, limit_remaining_cents: int} */
    private function figures(Card $card): array
    {
        return resolve(ShowCardAvailable::class)->handle($card->card_token, CarbonImmutable::now())
            ?? ['available_cents' => 0, 'limit_remaining_cents' => 0];
    }
}
