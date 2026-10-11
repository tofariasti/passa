<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Actions\RegisterDeposit;
use App\Actions\ShowCompanyStatement;
use App\Enums\CompanyEntryType;
use App\Models\CompanyEntry;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

final class CompanyStatementPage extends Page implements HasTable
{
    use RendersAdminTable;

    protected static ?string $slug = 'company-statement';

    protected static ?string $title = 'Extrato da empresa';

    protected static ?string $navigationLabel = 'Extrato da empresa';

    protected static ?int $navigationSort = 2;

    protected static string|UnitEnum|null $navigationGroup = 'Acme';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public function table(Table $table): Table
    {
        return $table
            ->query(CompanyEntry::query()->oldest('occurred_at')->orderBy('id'))
            ->paginated(false)
            ->emptyStateHeading('Nenhum lançamento')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Quando')
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof CarbonInterface
                        ? $state->utc()->format('Y-m-d\TH:i:s\Z')
                        : ''),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (CompanyEntryType $state): string => match ($state) {
                        CompanyEntryType::Deposit => 'Depósito',
                        CompanyEntryType::Capture => 'Captura',
                    }),
                TextColumn::make('amount_cents')
                    ->label('Valor (centavos)')
                    ->formatStateUsing(fn (int $state): string => (string) $state),
                TextColumn::make('balance_after_cents')
                    ->label('Saldo depois (centavos)')
                    ->state(function (CompanyEntry $record): string {
                        foreach ($this->statement()['lines'] as $line) {
                            if ($line['id'] === $record->id) {
                                return (string) $line['balance_after_cents'];
                            }
                        }

                        return '';
                    }),
                TextColumn::make('reference')->label('Referência')->placeholder('—'),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make(fn (): string => 'Disponível atual: '.$this->statement()['available_cents'].' centavos'),
            Text::make(fn (): string => 'Saldo: '.$this->statement()['balance_cents'].' centavos'),
            EmbeddedTable::make(),
        ]);
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('deposit')
                ->label('Registrar depósito')
                ->schema([
                    TextInput::make('amount_cents')
                        ->label('Valor (centavos)')
                        ->required()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(100_000_000)
                        ->validationMessages([
                            'required' => 'Informe o valor em centavos.',
                            'integer' => 'O valor precisa ser um número inteiro de centavos.',
                            'min' => 'O valor mínimo é 1 centavo.',
                            'max' => 'O valor máximo é 100000000 centavos.',
                        ]),
                ])
                ->action(function (array $data, RegisterDeposit $deposit): void {
                    $deposit->handle((int) $data['amount_cents']);
                }),
        ];
    }

    /**
     * @return array{available_cents: int, balance_cents: int, lines: list<array{id: int, occurred_at: string, type: string, amount_cents: int, balance_after_cents: int, reference: string|null}>}
     */
    private function statement(): array
    {
        return resolve(ShowCompanyStatement::class)->handle();
    }
}
