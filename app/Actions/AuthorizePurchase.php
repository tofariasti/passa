<?php

declare(strict_types=1);

namespace App\Actions;

use App\Domain\Authorization\AuthorizationMessage;
use App\Domain\Authorization\DeclinePurchase;
use App\Domain\Ledger\Balances;
use App\Enums\CardStatus;
use App\Enums\CardTransactionType;
use App\Enums\DeclineReason;
use App\Enums\PurchaseDecision;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Company;
use App\Models\NetworkMessage;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

final readonly class AuthorizePurchase
{
    public function __construct(
        private DeclinePurchase $declinePurchase,
        private RecordPurchaseEvent $events,
    ) {}

    /** @return array{status: int, body: array<string, string>} */
    public function handle(AuthorizationMessage $message): array
    {
        return DB::transaction(function () use ($message): array {
            $company = Company::query()->orderBy('id')->lockForUpdate()->firstOrFail();

            $existing = NetworkMessage::query()->where('network_id', $message->id)->lockForUpdate()->first();

            if ($existing?->response_body !== null && $existing->http_status !== null) {
                return [
                    'status' => $existing->http_status,
                    'body' => $existing->response_body,
                ];
            }

            $card = Card::query()->where('card_token', $message->cardToken)->lockForUpdate()->first();
            $reason = $this->declinePurchase->reason(
                cardExists: $card !== null,
                cardBlocked: $card?->status === CardStatus::Blocked,
                blockedMccs: $card === null ? [] : $card->blocked_mccs,
                purchaseLimitCents: $card?->purchase_limit_cents,
                amountCents: $message->amountCents,
                mcc: $message->mcc,
                limitRemainingCents: $card === null ? 0 : Balances::limitRemaining($card, $message->occurredAt),
                companyAvailableCents: Balances::companyAvailable($company),
            );

            $purchase = Purchase::query()->create([
                'card_id' => $card?->id,
                'network_authorization_id' => $message->id,
                'amount_cents' => $message->amountCents,
                'currency' => 'BRL',
                'mcc' => $message->mcc,
                'merchant_name' => $message->merchantName,
                'merchant_city' => $message->merchantCity,
                'merchant_country' => $message->merchantCountry,
                'occurred_at' => $message->occurredAt,
                'decision' => $reason instanceof DeclineReason ? PurchaseDecision::Declined : PurchaseDecision::Approved,
                'reason' => $reason,
            ]);

            if (! $reason instanceof DeclineReason && $card !== null) {
                CardTransaction::query()->create([
                    'card_id' => $card->id,
                    'purchase_id' => $purchase->id,
                    'occurred_at' => $message->occurredAt,
                    'type' => CardTransactionType::Hold,
                    'amount_cents' => -$message->amountCents,
                    'reference' => $message->id,
                ]);
            }

            $body = $reason instanceof DeclineReason
                ? ['decision' => 'declined', 'reason' => $reason->value]
                : ['decision' => 'approved'];

            NetworkMessage::query()->create([
                'purchase_id' => $purchase->id,
                'network_id' => $message->id,
                'http_status' => 200,
                'response_body' => $body,
            ]);

            $this->events->applyStored($purchase, $company);

            return ['status' => 200, 'body' => $body];
        });
    }
}
