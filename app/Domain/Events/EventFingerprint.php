<?php

declare(strict_types=1);

namespace App\Domain\Events;

final class EventFingerprint
{
    public static function hash(EventMessage $message): string
    {
        $parts = [
            $message->type->value,
            $message->authorizationId,
            $message->occurredAt->utc()->format('Y-m-d\TH:i:s\Z'),
            $message->amountCents === null ? '' : (string) $message->amountCents,
            $message->currency ?? '',
            $message->sequence === null ? '' : (string) $message->sequence,
            $message->final === null ? '' : ($message->final ? '1' : '0'),
        ];

        return hash('sha256', implode('|', $parts));
    }
}
