<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Actions\ListDeclinedPurchases;
use App\Enums\DeclineReason;
use App\Models\Purchase;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

final class DeclinedPurchasesPage extends Page implements HasTable
{
    use RendersAdminTable;

    protected static ?string $slug = 'declined-purchases';

    protected static ?string $title = 'Autorizações recusadas';

    protected static ?string $navigationLabel = 'Recusadas';

    protected static ?int $navigationSort = 4;

    protected static string|UnitEnum|null $navigationGroup = 'Acme';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => resolve(ListDeclinedPurchases::class)->query()->with('card'))
            ->paginated(false)
            ->emptyStateHeading('Nenhuma autorização recusada')
            ->columns([
                TextColumn::make('network_authorization_id')->label('Autorização'),
                TextColumn::make('card.card_token')->label('Cartão')->placeholder('—'),
                TextColumn::make('amount_cents')
                    ->label('Valor (centavos)')
                    ->formatStateUsing(fn (int $state): string => (string) $state),
                TextColumn::make('reason')
                    ->label('Motivo')
                    ->formatStateUsing(fn (DeclineReason $state): string => $state->value),
            ])
            ->recordActions([
                Action::make('history')
                    ->label('História')
                    ->url(fn (Purchase $record): string => PurchaseHistoryPage::getUrl(['purchase' => $record->id])),
            ]);
    }
}
