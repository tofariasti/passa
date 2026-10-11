<?php

declare(strict_types=1);

namespace App\Domain\Network;

use Carbon\CarbonImmutable;

final class OccurredAt
{
    public static function parse(?string $value, int $sentAt): ?CarbonImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) !== 1) {
            return null;
        }

        $parsed = CarbonImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, 'UTC');

        if (! $parsed instanceof CarbonImmutable || $parsed->format('Y-m-d\TH:i:s\Z') !== $value || $parsed->getTimestamp() > $sentAt) {
            return null;
        }

        return $parsed;
    }
}
