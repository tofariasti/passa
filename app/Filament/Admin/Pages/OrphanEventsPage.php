<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Actions\ListOrphanEvents;
use App\Enums\PurchaseEventType;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

final class OrphanEventsPage extends Page implements HasTable
{
    use RendersAdminTable;

    protected static ?string $slug = 'orphan-events';

    protected static ?string $title = 'Events sem autorização';

    protected static ?string $navigationLabel = 'Events sem autorização';

    protected static ?int $navigationSort = 6;

    protected static string|UnitEnum|null $navigationGroup = 'Acme';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => resolve(ListOrphanEvents::class)->query())
            ->paginated(false)
            ->emptyStateHeading('Nenhum event sem autorização')
            ->columns([
                TextColumn::make('network_id')->label('Event'),
                TextColumn::make('authorization_network_id')->label('Autorização'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (PurchaseEventType $state): string => match ($state) {
                        PurchaseEventType::Capture => 'Captura',
                        PurchaseEventType::Cancellation => 'Cancelamento',
                    }),
                TextColumn::make('occurred_at')
                    ->label('Quando')
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof CarbonInterface
                        ? $state->utc()->format('Y-m-d\TH:i:s\Z')
                        : ''),
                TextColumn::make('amount_cents')
                    ->label('Valor (centavos)')
                    ->placeholder('—'),
            ]);
    }
}
