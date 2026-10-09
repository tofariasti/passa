<?php

declare(strict_types=1);

namespace App\Actions;

use App\Domain\Authorization\OpenHold;
use App\Domain\Events\EventFingerprint;
use App\Domain\Events\EventMessage;
use App\Enums\CardTransactionType;
use App\Enums\CompanyEntryType;
use App\Enums\PurchaseDecision;
use App\Enums\PurchaseEventType;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Company;
use App\Models\CompanyEntry;
use App\Models\NetworkMessage;
use App\Models\Purchase;
use App\Models\PurchaseEvent;
use Illuminate\Support\Facades\DB;

final readonly class RecordPurchaseEvent
{
    /** @var array{received: true} */
    private const array BODY = ['received' => true];

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(EventMessage $message): array
    {
        return DB::transaction(function () use ($message): array {
            $company = Company::query()->orderBy('id')->lockForUpdate()->firstOrFail();
            $stored = $this->stored($message->id);

            if ($stored !== null) {
                return $stored;
            }

            $fingerprint = EventFingerprint::hash($message);
            $original = NetworkMessage::query()->where('fact_fingerprint', $fingerprint)->lockForUpdate()->first();

            if ($original instanceof NetworkMessage && is_int($original->http_status) && is_array($original->response_body)) {
                NetworkMessage::query()->create([
                    'purchase_id' => $original->purchase_id,
                    'network_id' => $message->id,
                    'http_status' => $original->http_status,
                    'response_body' => $original->response_body,
                ]);

                return ['status' => $original->http_status, 'body' => $original->response_body];
            }

            $purchase = Purchase::query()
                ->where('network_authorization_id', $message->authorizationId)
                ->lockForUpdate()
                ->first();

            if ($purchase instanceof Purchase && $purchase->card_id !== null) {
                Card::query()->whereKey($purchase->card_id)->lockForUpdate()->first();
            }

            $event = PurchaseEvent::query()->create([
                'purchase_id' => $purchase?->id,
                'network_id' => $message->id,
                'authorization_network_id' => $message->authorizationId,
                'type' => $message->type,
                'occurred_at' => $message->occurredAt,
                'amount_cents' => $message->amountCents,
                'currency' => $message->currency,
                'sequence' => $message->sequence,
                'is_final' => $message->final,
            ]);

            NetworkMessage::query()->create([
                'purchase_id' => $purchase?->id,
                'network_id' => $message->id,
                'fact_fingerprint' => $fingerprint,
                'http_status' => 200,
                'response_body' => self::BODY,
            ]);

            if ($purchase instanceof Purchase) {
                $this->apply($purchase, $event, $company);
            }

            return ['status' => 200, 'body' => self::BODY];
        });
    }

    public function applyStored(Purchase $purchase, Company $company): void
    {
        $events = PurchaseEvent::query()
            ->where('authorization_network_id', $purchase->network_authorization_id)
            ->whereNull('purchase_id')
            ->orderByRaw('sequence asc nulls last')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($events as $event) {
            $event->update(['purchase_id' => $purchase->id]);
            NetworkMessage::query()->where('network_id', $event->network_id)->update(['purchase_id' => $purchase->id]);
            $this->apply($purchase, $event, $company);
        }
    }

    private function apply(Purchase $purchase, PurchaseEvent $event, Company $company): void
    {
        if ($purchase->decision !== PurchaseDecision::Approved || $purchase->card_id === null) {
            return;
        }

        if ($event->type === PurchaseEventType::Capture && is_int($event->amount_cents)) {
            CompanyEntry::query()->create([
                'company_id' => $company->id,
                'purchase_id' => $purchase->id,
                'purchase_event_id' => $event->id,
                'occurred_at' => $event->occurred_at,
                'type' => CompanyEntryType::Capture,
                'amount_cents' => -$event->amount_cents,
                'reference' => $event->network_id,
            ]);
        }

        $this->alignCard($purchase, $event);
    }

    private function alignCard(Purchase $purchase, PurchaseEvent $cause): void
    {
        if ($purchase->card_id === null) {
            return;
        }

        $events = PurchaseEvent::query()->where('purchase_id', $purchase->id)->get();
        $captures = $events->filter(
            fn (PurchaseEvent $event): bool => $event->type === PurchaseEventType::Capture,
        );
        $captured = (int) $captures->sum('amount_cents');
        $openHold = OpenHold::cents(
            authorizedCents: $purchase->amount_cents,
            approved: $purchase->decision === PurchaseDecision::Approved,
            cancelled: $events->contains(
                fn (PurchaseEvent $event): bool => $event->type === PurchaseEventType::Cancellation,
            ),
            capturedCents: $captured,
            final: $captures->contains(fn (PurchaseEvent $event): bool => $event->is_final === true),
        );
        $current = (int) CardTransaction::query()->where('purchase_id', $purchase->id)->sum('amount_cents');
        $delta = -($openHold + $captured) - $current;

        if ($delta === 0) {
            return;
        }

        CardTransaction::query()->create([
            'card_id' => $purchase->card_id,
            'purchase_id' => $purchase->id,
            'occurred_at' => $cause->occurred_at,
            'type' => CardTransactionType::Adjustment,
            'amount_cents' => $delta,
            'reference' => $cause->network_id,
        ]);
    }

    /** @return array{status: int, body: array<string, mixed>}|null */
    private function stored(string $networkId): ?array
    {
        $existing = NetworkMessage::query()->where('network_id', $networkId)->lockForUpdate()->first();

        if (! $existing instanceof NetworkMessage || ! is_int($existing->http_status) || ! is_array($existing->response_body)) {
            return null;
        }

        return ['status' => $existing->http_status, 'body' => $existing->response_body];
    }
}
