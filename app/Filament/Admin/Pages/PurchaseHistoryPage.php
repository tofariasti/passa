<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Actions\ShowPurchaseHistory;
use App\Models\Purchase;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

final class PurchaseHistoryPage extends Page
{
    public Purchase $purchaseModel;

    protected static ?string $slug = 'purchases/{purchase}/history';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(int $purchase): void
    {
        $found = Purchase::query()->find($purchase);

        abort_unless($found instanceof Purchase, 404);

        $this->purchaseModel = $found;
    }

    public function getTitle(): string
    {
        return 'Compra '.$this->purchaseModel->network_authorization_id;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.admin.purchase-history')
                ->viewData(fn (): array => [
                    'history' => $this->history(),
                ]),
        ]);
    }

    /**
     * @return array{network_id: string, decision: string, reason: string|null, lines: list<array{occurred_at: string, kind: string, amount_cents: int|null, reference: string, sequence: int|null, final: bool|null}>}
     */
    public function history(): array
    {
        $history = resolve(ShowPurchaseHistory::class)->handle($this->purchaseModel->id);

        abort_if($history === null, 404);

        return $history;
    }
}
