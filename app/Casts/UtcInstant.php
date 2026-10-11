<?php

declare(strict_types=1);

namespace App\Casts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Network instants are UTC. The app timezone is the Acme clock, so a naive timestamp must not be read as Sao Paulo.
 *
 * @implements CastsAttributes<CarbonImmutable|null, CarbonImmutable|string|null>
 */
final class UtcInstant implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value, 'UTC')->utc();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s');
    }
}
