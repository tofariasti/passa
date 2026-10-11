<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use App\Domain\Network\OccurredAt;
use Carbon\CarbonImmutable;
use JsonException;

final class ParseAuthorization
{
    public function __invoke(string $rawBody, int $sentAt): ?AuthorizationMessage
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
        $cardToken = $data['card_token'] ?? null;
        $amount = $data['amount_cents'] ?? null;
        $mcc = $data['mcc'] ?? null;
        $merchant = $data['merchant'] ?? null;

        if (! is_string($id) || $id === '' || mb_strlen($id) > 64 || ! is_string($cardToken) || $cardToken === '') {
            return null;
        }

        if (! is_int($amount) || $amount < 1 || $amount > 100_000_000) {
            return null;
        }

        if (($data['currency'] ?? null) !== 'BRL' || ! is_string($mcc) || preg_match('/^\d{4}$/', $mcc) !== 1) {
            return null;
        }

        if (! is_array($merchant) || array_is_list($merchant)) {
            return null;
        }

        $name = $merchant['name'] ?? null;
        $city = $merchant['city'] ?? null;
        $country = $merchant['country'] ?? null;

        if (! is_string($name) || $name === '' || ! is_string($city) || $city === '' || ! is_string($country) || preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            return null;
        }

        $occurredAt = OccurredAt::parse(is_string($data['occurred_at'] ?? null) ? $data['occurred_at'] : null, $sentAt);

        if (! $occurredAt instanceof CarbonImmutable) {
            return null;
        }

        return new AuthorizationMessage(
            id: $id,
            cardToken: $cardToken,
            amountCents: $amount,
            mcc: $mcc,
            merchantName: $name,
            merchantCity: $city,
            merchantCountry: $country,
            occurredAt: $occurredAt,
        );
    }
}
