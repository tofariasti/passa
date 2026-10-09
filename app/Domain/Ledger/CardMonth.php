<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use Carbon\CarbonImmutable;

final class CardMonth
{
    public static function parse(?string $month, CarbonImmutable $now): ?CarbonImmutable
    {
        if ($month === null) {
            return $now->timezone('America/Sao_Paulo')->startOfMonth();
        }

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            return null;
        }

        $start = CarbonImmutable::createFromFormat('!Y-m', $month, 'America/Sao_Paulo');

        if (! $start instanceof CarbonImmutable || $start->format('Y-m') !== $month) {
            return null;
        }

        return $start->startOfMonth();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function utcBounds(CarbonImmutable $monthStart): array
    {
        $local = $monthStart->timezone('America/Sao_Paulo')->startOfMonth();

        return [$local->utc(), $local->addMonth()->utc()];
    }
}
