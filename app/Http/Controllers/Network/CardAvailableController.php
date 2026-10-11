<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Actions\ShowCardAvailable;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

final class CardAvailableController extends Controller
{
    public function show(string $cardToken, ShowCardAvailable $show): JsonResponse
    {
        $body = $show->handle($cardToken, CarbonImmutable::now());

        if ($body === null) {
            return response()->json(['message' => 'Card not found.'], 404);
        }

        return response()->json($body);
    }
}
