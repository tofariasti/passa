<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\PurchaseDecision;
use App\Models\Purchase;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

final class PurchasesPage extends Page implements HasTable
{
    use RendersAdminTable;

    protected static ?string $slug = 'purchases';

    protected static ?string $title = 'Compras';

    protected static ?string $navigationLabel = 'Compras';

    protected static ?int $navigationSort = 3;

    protected static string|UnitEnum|null $navigationGroup = 'Acme';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public function table(Table $table): Table
    {
        return $table
            ->query(Purchase::query()->with('card')->oldest('occurred_at')->orderBy('id'))
            ->paginated(false)
            ->emptyStateHeading('Nenhuma compra')
            ->columns([
                TextColumn::make('network_authorization_id')->label('Autorização'),
                TextColumn::make('card.card_token')->label('Cartão')->placeholder('—'),
                TextColumn::make('amount_cents')
                    ->label('Valor (centavos)')
                    ->formatStateUsing(fn (int $state): string => (string) $state),
                TextColumn::make('decision')
                    ->label('Decisão')
                    ->formatStateUsing(fn (PurchaseDecision $state): string => match ($state) {
                        PurchaseDecision::Approved => 'Aprovada',
                        PurchaseDecision::Declined => 'Recusada',
                    }),
                TextColumn::make('reason')->label('Motivo')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('history')
                    ->label('História')
                    ->url(fn (Purchase $record): string => PurchaseHistoryPage::getUrl(['purchase' => $record->id])),
            ]);
    }
}
