<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\ShowCardAvailable;
use App\Actions\ShowCardStatement;
use App\Actions\ShowPurchaseHistory;
use App\Domain\Ledger\CardMonth;
use App\Models\Card;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.cardholder')]
#[Title('Meu cartão')]
final class MyCard extends Component
{
    public function mount(): void
    {
        $this->card();
    }

    public function render(): View
    {
        $card = $this->card();
        $now = CarbonImmutable::now();
        $available = resolve(ShowCardAvailable::class)->handle($card->card_token, $now);
        $statement = resolve(ShowCardStatement::class)->handle(
            $card->card_token,
            CardMonth::parse(null, $now) ?? $now,
        );

        abort_if($available === null || $statement === null, 403);

        $histories = [];

        foreach ($card->purchases()->oldest('occurred_at')->orderBy('id')->get() as $purchase) {
            $history = resolve(ShowPurchaseHistory::class)->handle($purchase->id);

            if ($history !== null) {
                $histories[] = $history;
            }
        }

        return view('livewire.my-card', [
            'cardToken' => $card->card_token,
            'available' => $available,
            'statement' => $statement,
            'histories' => $histories,
        ]);
    }

    private function card(): Card
    {
        $user = Auth::user();
        $card = $user instanceof User ? $user->card : null;

        abort_unless($card instanceof Card, 403);

        return $card;
    }
}
