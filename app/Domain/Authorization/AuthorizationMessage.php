<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use Carbon\CarbonImmutable;

final class AuthorizationMessage
{
    public function __construct(
        public string $id,
        public string $cardToken,
        public int $amountCents,
        public string $mcc,
        public string $merchantName,
        public string $merchantCity,
        public string $merchantCountry,
        public CarbonImmutable $occurredAt,
    ) {}
}
