<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Company extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name',
    ];

    /** @return HasMany<Card, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /** @return HasMany<CompanyEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(CompanyEntry::class);
    }
}
