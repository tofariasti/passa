<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

final class OpenHold
{
    public static function cents(int $authorizedCents, bool $approved, bool $cancelled, int $capturedCents, bool $final): int
    {
        if (! $approved || $cancelled || $final) {
            return 0;
        }

        return max($authorizedCents - $capturedCents, 0);
    }
}
