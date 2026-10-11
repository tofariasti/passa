<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\Network\OccurredAt;
use App\Enums\PurchaseEventType;
use Carbon\CarbonImmutable;
use JsonException;

final class ParseEvent
{
    public function __invoke(string $rawBody, int $sentAt): ?EventMessage
    {
        try {
            $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data) || array_is_list($data)) {
            return null;
        }

        $id = $data['id'] ?? null;
        $authorizationId = $data['authorization_id'] ?? null;
        $type = $data['type'] ?? null;

        if (! is_string($id) || $id === '' || mb_strlen($id) > 64 || ! is_string($authorizationId) || $authorizationId === '' || mb_strlen($authorizationId) > 64) {
            return null;
        }

        if ($type !== PurchaseEventType::Capture->value && $type !== PurchaseEventType::Cancellation->value) {
            return null;
        }

        $occurredAt = OccurredAt::parse(is_string($data['occurred_at'] ?? null) ? $data['occurred_at'] : null, $sentAt);

        if (! $occurredAt instanceof CarbonImmutable) {
            return null;
        }

        $eventType = PurchaseEventType::from($type);

        if ($eventType === PurchaseEventType::Cancellation) {
            return new EventMessage(
                id: $id,
                type: $eventType,
                occurredAt: $occurredAt,
                authorizationId: $authorizationId,
                amountCents: null,
                currency: null,
                sequence: null,
                final: null,
            );
        }

        $amount = $data['amount_cents'] ?? null;
        $sequence = $data['sequence'] ?? null;

        if (! is_int($amount) || $amount < 1 || $amount > 100_000_000 || ($data['currency'] ?? null) !== 'BRL') {
            return null;
        }

        $final = $data['final'] ?? null;

        if (! is_int($sequence) || $sequence < 1 || $sequence > 2_147_483_647 || ! is_bool($final)) {
            return null;
        }

        return new EventMessage(
            id: $id,
            type: $eventType,
            occurredAt: $occurredAt,
            authorizationId: $authorizationId,
            amountCents: $amount,
            currency: 'BRL',
            sequence: $sequence,
            final: $final,
        );
    }
}
