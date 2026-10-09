<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseEventType: string
{
    case Capture = 'capture';
    case Cancellation = 'cancellation';
}
