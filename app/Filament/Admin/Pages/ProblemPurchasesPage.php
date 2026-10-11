<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Actions\ListProblemPurchases;
use App\Models\Purchase;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

final class ProblemPurchasesPage extends Page implements HasTable
{
    use RendersAdminTable;

    protected static ?string $slug = 'problem-purchases';

    protected static ?string $title = 'Compras com problema';

    protected static ?string $navigationLabel = 'Problemas';

    protected static ?int $navigationSort = 5;

    protected static string|UnitEnum|null $navigationGroup = 'Acme';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => resolve(ListProblemPurchases::class)->query()->with('card'))
            ->paginated(false)
            ->emptyStateHeading('Nenhuma compra com problema')
            ->columns([
                TextColumn::make('network_authorization_id')->label('Autorização'),
                TextColumn::make('card.card_token')->label('Cartão')->placeholder('—'),
                TextColumn::make('mcc')->label('MCC'),
                TextColumn::make('amount_cents')
                    ->label('Autorizado (centavos)')
                    ->formatStateUsing(fn (int $state): string => (string) $state),
            ])
            ->recordActions([
                Action::make('history')
                    ->label('História')
                    ->url(fn (Purchase $record): string => PurchaseHistoryPage::getUrl(['purchase' => $record->id])),
            ]);
    }
}
