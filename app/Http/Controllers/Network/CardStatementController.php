<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Actions\ShowCardStatement;
use App\Domain\Ledger\CardMonth;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CardStatementController extends Controller
{
    public function show(Request $request, string $cardToken, ShowCardStatement $show): JsonResponse
    {
        $month = $request->query('month');

        if (is_array($month)) {
            return response()->json(['message' => 'The message does not follow the contract.'], 422);
        }

        $start = CardMonth::parse(is_string($month) ? $month : null, CarbonImmutable::now());

        if (! $start instanceof CarbonImmutable) {
            return response()->json(['message' => 'The message does not follow the contract.'], 422);
        }

        $body = $show->handle($cardToken, $start);

        if ($body === null) {
            return response()->json(['message' => 'Card not found.'], 404);
        }

        return response()->json($body);
    }
}
