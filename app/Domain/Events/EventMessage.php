<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Enums\PurchaseEventType;
use Carbon\CarbonImmutable;

final readonly class EventMessage
{
    public function __construct(
        public string $id,
        public PurchaseEventType $type,
        public CarbonImmutable $occurredAt,
        public string $authorizationId,
        public ?int $amountCents,
        public ?string $currency,
        public ?int $sequence,
        public ?bool $final,
    ) {}
}
