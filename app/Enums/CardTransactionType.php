<?php

declare(strict_types=1);

namespace App\Enums;

enum CardTransactionType: string
{
    case Hold = 'hold';
    case Adjustment = 'adjustment';
}
