<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseDecision: string
{
    case Approved = 'approved';
    case Declined = 'declined';
}
